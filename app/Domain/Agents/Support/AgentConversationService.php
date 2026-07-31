<?php

namespace App\Domain\Agents\Support;

use App\Domain\Agents\Contracts\AgentToolContract;
use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Models\AgentMessage;
use App\Domain\Agents\Models\AgentThread;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Throwable;

/**
 * Runs the tool-calling loop for one turn of a thread: build the message
 * history + system prompt, ask OpenAI, execute any requested tool calls,
 * feed the results back, and repeat until the model replies with plain
 * text (or the iteration cap is hit). Every step is persisted as an
 * AgentMessage so the thread can be replayed/resumed exactly as sent.
 *
 * This is the hand-rolled equivalent of Postiz's Mastra agent + tool
 * loader - no PHP framework fills that role, so this is pragmatic custom
 * code rather than a package.
 */
class AgentConversationService
{
    public function __construct(private readonly OpenAiService $openAi) {}

    public function respond(AgentThread $thread): void
    {
        $organization = $thread->organization;
        $user = $thread->user;
        $tools = $this->toolDefinitions();

        for ($i = 0; $i < config('agents.max_tool_iterations'); $i++) {
            $message = $this->openAi->chat(
                $this->buildMessages($thread, $organization),
                $tools,
            )->choices[0]->message;

            if ($message->toolCalls === []) {
                $thread->messages()->create([
                    'role' => AgentMessageRole::Assistant,
                    'content' => $message->content,
                ]);

                return;
            }

            $thread->messages()->create([
                'role' => AgentMessageRole::Assistant,
                'content' => $message->content,
                'tool_calls' => array_map(fn ($call) => [
                    'id' => $call->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $call->function->name,
                        'arguments' => $call->function->arguments,
                    ],
                ], $message->toolCalls),
            ]);

            foreach ($message->toolCalls as $call) {
                $thread->messages()->create([
                    'role' => AgentMessageRole::Tool,
                    'tool_name' => $call->function->name,
                    'tool_call_id' => $call->id,
                    'content' => json_encode(
                        $this->executeTool($call->function->name, $call->function->arguments, $organization, $user)
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

        foreach ($thread->messages as $message) {
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
                'content' => $message->content,
            ],
        };
    }

    private function systemPrompt(Organization $organization): string
    {
        return <<<PROMPT
        You are Pluma's social media assistant for the organization "{$organization->name}".
        You help the user schedule social media posts, generate images for those posts, and see which channels are connected.
        - Always call list_channels before scheduling if you don't already know the channel id from this conversation.
        - Before calling schedule_post, restate the channel, content, and (if scheduling) the time back to the user and get explicit confirmation first.
        - Keep replies concise and conversational.
        PROMPT;
    }
}
