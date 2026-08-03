<?php

namespace App\Domain\Agents\Support;

use App\Domain\Agents\Contracts\AgentToolContract;
use App\Domain\Agents\Contracts\ChatCompletionContract;
use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Models\AgentMessage;
use App\Domain\Agents\Models\AgentThread;
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
                $this->toolDefinitions($thread),
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
                        $this->executeTool($call->name, $call->argumentsJson, $organization, $user)
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
    private function executeTool(string $name, string $argumentsJson, Organization $organization, User $user): array
    {
        $tool = $this->resolveTool($name);

        if (! $tool) {
            return ['error' => "Unknown tool: {$name}"];
        }

        try {
            return $tool->handle(json_decode($argumentsJson, true) ?? [], $organization, $user);
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
    private function toolDefinitions(AgentThread $thread): array
    {
        // Belt-and-suspenders on top of buildMessages()'s fresh query below:
        // list_channels is a pure lookup, so once it's succeeded earlier in
        // this thread, stop even offering it rather than relying solely on
        // the system prompt to stop the model re-calling it.
        $alreadyListedChannels = $thread->messages()
            ->where('role', AgentMessageRole::Tool)
            ->where('tool_name', 'list_channels')
            ->exists();

        return collect(config('agents.tools'))
            ->map(fn (string $class) => App::make($class))
            ->reject(fn (AgentToolContract $tool) => $alreadyListedChannels && $tool->name() === 'list_channels')
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
        // actually causing the model to repeat list_channels every
        // iteration - it never saw evidence it had already been called.
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
        return <<<PROMPT
        You are Pluma's social media assistant for the organization "{$organization->name}".
        You help the user schedule social media posts, generate images for those posts, and see which channels are connected.
        - Call list_channels at most once per turn - if you already called it earlier in this same conversation, reuse that result instead of calling it again, even across multiple tool calls in the same turn.
        - If the user's request needs an image, call generate_image next.
        - Once you have the channel and (if needed) a generated image, reply with plain text restating the channel, content, and (if scheduling) the time, and ask the user to confirm before you call schedule_post. Do not call schedule_post in the same turn you ask for confirmation.
        - Keep replies concise and conversational.
        PROMPT;
    }
}
