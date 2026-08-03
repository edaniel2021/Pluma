<?php

namespace App\Domain\Agents\Support;

use App\Domain\Agents\Contracts\ChatCompletionContract;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Thin wrapper over openai-php/laravel - the conversation loop and tools
 * call these two methods rather than the OpenAI facade directly, so the
 * model/config choices live in one place.
 */
class OpenAiService implements ChatCompletionContract
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function chat(array $messages, array $tools = []): ChatCompletionResult
    {
        // Explicitly rebuilt (not passed through as-is) so a neutral field
        // only Gemini's translation needs (tool_name, see GeminiService)
        // doesn't get forwarded to OpenAI's API as an unrecognized key.
        $openAiMessages = array_map(fn (array $message) => match ($message['role']) {
            'tool' => [
                'role' => 'tool',
                'tool_call_id' => $message['tool_call_id'],
                'content' => $message['content'],
            ],
            default => array_filter([
                'role' => $message['role'],
                'content' => $message['content'] ?? null,
                'tool_calls' => $message['tool_calls'] ?? null,
            ], fn ($value) => $value !== null),
        }, $messages);

        $message = OpenAI::chat()->create(array_filter([
            'model' => config('agents.model'),
            'messages' => $openAiMessages,
            'tools' => $tools ?: null,
        ]))->choices[0]->message;

        return new ChatCompletionResult(
            content: $message->content,
            toolCalls: array_map(fn ($call) => new ChatToolCall(
                id: $call->id,
                name: $call->function->name,
                argumentsJson: $call->function->arguments,
            ), $message->toolCalls),
        );
    }

    /**
     * Returns base64-encoded image data (PNG).
     */
    public function generateImage(string $prompt): string
    {
        $response = OpenAI::images()->create([
            'model' => config('agents.openai_image_model'),
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'response_format' => 'b64_json',
        ]);

        return $response->data[0]->b64_json;
    }
}
