<?php

namespace App\Domain\Agents\Support;

use App\Domain\Agents\Contracts\AgentToolContract;
use App\Domain\Agents\Contracts\ChatCompletionContract;
use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Models\AgentMessage;
use App\Domain\Agents\Models\AgentThread;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Throwable;

/**
 * Runs the tool-calling loop for one turn of a thread: build the message
 * history + system prompt, ask the configured chat provider (see
 * ChatCompletionContract - OpenAI or Gemini, config('agents.chat_provider')),
 * execute any requested tool calls, feed the results back, and repeat until
 * the model replies with plain text (or the iteration cap is hit). Every
 * step is persisted as an AgentMessage so the thread can be replayed/
 * resumed exactly as sent.
 *
 * This is the hand-rolled equivalent of Postiz's Mastra agent + tool
 * loader - no PHP framework fills that role, so this is pragmatic custom
 * code rather than a package.
 */
class AgentConversationService
{
    public function __construct(private readonly ChatCompletionContract $chatCompletions) {}

    public function respond(AgentThread $thread): void
    {
        $organization = $thread->organization;
        $user = $thread->user;

        for ($i = 0; $i < config('agents.max_tool_iterations'); $i++) {
            $result = $this->chatCompletions->chat(
                $this->buildMessages($thread, $organization),
                $this->toolDefinitions(),
            );

            if ($result->toolCalls === []) {
                $thread->messages()->create([
                    'role' => AgentMessageRole::Assistant,
                    'content' => $result->content,
                ]);

                return;
            }

            $thread->messages()->create([
                'role' => AgentMessageRole::Assistant,
                'content' => $result->content,
                'tool_calls' => array_map(fn (ChatToolCall $call) => [
                    'id' => $call->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $call->name,
                        'arguments' => $call->argumentsJson,
                    ],
                ], $result->toolCalls),
            ]);

            foreach ($result->toolCalls as $call) {
                $thread->messages()->create([
                    'role' => AgentMessageRole::Tool,
                    'tool_name' => $call->name,
                    'tool_call_id' => $call->id,
                    'content' => json_encode(
                        $this->executeTool($call->name, $call->argumentsJson, $organization, $user, $thread)
                    ),
                ]);
            }
        }

        $thread->messages()->create([
            'role' => AgentMessageRole::Assistant,
            'content' => "I've hit the tool-call limit for this turn - could you rephrase or confirm what you'd like me to do next?",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function executeTool(string $name, string $argumentsJson, Organization $organization, User $user, AgentThread $thread): array
    {
        $tool = $this->resolveTool($name);

        if (! $tool) {
            return ['error' => "Unknown tool: {$name}"];
        }

        try {
            return $tool->handle(json_decode($argumentsJson, true) ?? [], $organization, $user, $thread);
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function resolveTool(string $name): ?AgentToolContract
    {
        return collect(config('agents.tools'))
            ->map(fn (string $class) => App::make($class))
            ->first(fn (AgentToolContract $tool) => $tool->name() === $name);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        return collect(config('agents.tools'))
            ->map(fn (string $class) => App::make($class))
            ->map(fn (AgentToolContract $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parameters(),
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(AgentThread $thread, Organization $organization): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($organization)],
        ];

        // A fresh query, not the cached $thread->messages relation: within
        // this same respond() call, earlier loop iterations create new
        // messages via $thread->messages()->create() (the relation method),
        // which does NOT update $thread->messages (the already-cached
        // property) - every iteration after the first would otherwise see
        // the exact same stale snapshot from before the loop even started,
        // missing its own prior tool calls entirely. That's what was
        // actually causing a real production bug: a tool call the model had
        // already made (back when a list_channels tool existed) repeating
        // every iteration, because it never saw evidence it had already
        // been called.
        foreach ($thread->messages()->orderBy('id')->get() as $message) {
            $messages[] = $this->toWireMessage($message);
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    private function toWireMessage(AgentMessage $message): array
    {
        return match ($message->role) {
            AgentMessageRole::User => ['role' => 'user', 'content' => $message->content],
            AgentMessageRole::Assistant => array_filter([
                'role' => 'assistant',
                'content' => $message->content,
                'tool_calls' => $message->tool_calls,
            ], fn ($value) => $value !== null),
            AgentMessageRole::Tool => [
                'role' => 'tool',
                'tool_call_id' => $message->tool_call_id,
                // Not part of OpenAI's own 'tool' message shape (OpenAiService
                // strips it before sending) - only Gemini's translation needs
                // the function name for its functionResponse part.
                'tool_name' => $message->tool_name,
                'content' => $message->content,
            ],
        };
    }

    private function systemPrompt(Organization $organization): string
    {
        // The model has no other way to know the actual current date/time -
        // without this, "today"/"tomorrow"/"in 2 hours" are pure guesses,
        // which in production landed posts on the wrong day (invisible on
        // the Launches calendar, since it's rendered around the real
        // today) or converted local time to UTC incorrectly. Given in the
        // org's own timezone since that's also the frame schedule_post's
        // scheduled_at expects (see that tool's parameters()).
        $now = now($organization->timezone)->format('l, Y-m-d H:i');

        // Inlined directly rather than exposed as a list_channels tool the
        // model has to call: it's a cheap, read-only, org-scoped lookup
        // that's needed at the start of nearly every scheduling turn, and
        // a tool call costs a full extra model round trip (decide to call
        // it -> tool runs -> decide what to do with the result) purely to
        // fetch data that's already known before the model says anything at
        // all. Real production impact: this was a large, avoidable chunk of
        // response latency on essentially every "schedule a post" turn.
        $channels = $organization->integrations->isEmpty()
            ? 'No channels are connected yet - tell the user to connect one first.'
            : $organization->integrations
                ->map(fn (Integration $integration) => "- id {$integration->id}: {$integration->provider} ({$integration->account_name})".($integration->isDisabled() ? ' [disabled]' : ''))
                ->implode("\n        ");

        return <<<PROMPT
        You are Pluma's social media assistant for the organization "{$organization->name}".
        The current date and time is {$now} ({$organization->timezone}). Always compute relative times ("today", "tomorrow", "in 2 hours", etc.) from this exact value - never assume or guess the current date.
        The organization's connected channels are:
        {$channels}
        You help the user schedule social media posts and generate images for those posts. Use the channel list above directly - do not ask the user for a channel id, and there is no tool to look channels up again.
        - If the user's request needs an image, call generate_image next.
        - Once you have the channel and (if needed) a generated image, reply with plain text restating the channel, content, and (if scheduling) the time, and ask the user to confirm before you call schedule_post. Do not call schedule_post in the same turn you ask for confirmation.
        - When scheduling, give schedule_post's scheduled_at as a plain local date-time in the organization's own timezone ({$organization->timezone}) - e.g. "2026-08-04 15:00". Do not convert it to UTC yourself; the system does that automatically.
        - Keep replies concise and conversational.
        PROMPT;
    }
}
