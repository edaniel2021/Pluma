<?php

namespace App\Domain\Agents\Support;

use App\Domain\Agents\Contracts\ChatCompletionContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Http-facade wrapper for Google's Generative Language API - no
 * official Google PHP SDK exists for Gemini (same reasoning as
 * FalService), so this hits the REST endpoint directly and translates
 * between our internal OpenAI-shaped wire messages/tool-calls (see
 * AgentConversationService) and Gemini's contents/functionCall format.
 *
 * Gemini's multi-turn function-calling shape differs from OpenAI's in a
 * few ways that matter here:
 * - no 'system' role in contents - system prompt is a separate top-level
 *   systemInstruction field
 * - 'model' role instead of 'assistant'
 * - no 'tool' role - a tool result is a 'user'-role turn containing a
 *   functionResponse part
 * - tool definitions are one {functionDeclarations: [...]} entry per
 *   request, not one {type: function, function: {...}} per tool
 */
class GeminiService implements ChatCompletionContract
{
    public function chat(array $messages, array $tools = []): ChatCompletionResult
    {
        [$systemInstruction, $contents] = $this->toGeminiContents($messages);

        $response = Http::withHeaders(['x-goog-api-key' => config('agents.gemini_api_key')])
            ->post(
                'https://generativelanguage.googleapis.com/v1beta/models/'.config('agents.gemini_model').':generateContent',
                array_filter([
                    'systemInstruction' => $systemInstruction,
                    'contents' => $contents,
                    'tools' => $tools ? [['functionDeclarations' => array_column($tools, 'function')]] : null,
                ])
            );

        if ($response->failed()) {
            throw new RuntimeException("Gemini API request failed: {$response->body()}");
        }

        $parts = $response->json('candidates.0.content.parts', []);

        $content = collect($parts)->pluck('text')->filter()->implode('');

        $toolCalls = collect($parts)
            ->filter(fn (array $part) => isset($part['functionCall']))
            ->map(fn (array $part) => new ChatToolCall(
                // Not every Gemini model version returns an id on the
                // functionCall part - fall back to a generated one so a
                // ChatToolCall's id is never missing downstream.
                id: $part['functionCall']['id'] ?? uniqid($part['functionCall']['name'].'-'),
                name: $part['functionCall']['name'],
                argumentsJson: json_encode($part['functionCall']['args'] ?? []),
            ))
            ->values()
            ->all();

        return new ChatCompletionResult(
            content: $content !== '' ? $content : null,
            toolCalls: $toolCalls,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{0: ?array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function toGeminiContents(array $messages): array
    {
        $systemInstruction = null;
        $contents = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemInstruction = ['parts' => [['text' => $message['content']]]];

                continue;
            }

            if ($message['role'] === 'tool') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $message['tool_name'],
                            'id' => $message['tool_call_id'],
                            // (object) cast, not the bare decoded array: an
                            // empty/associative PHP array always encodes as
                            // JSON `[]`, but Gemini's response field is a
                            // Struct (object-typed) - a no-result tool call
                            // would otherwise send `[]` where `{}` is
                            // required, and Gemini 400s with "Proto field
                            // is not repeating, cannot start list."
                            'response' => (object) (json_decode($message['content'], true) ?? []),
                        ],
                    ]],
                ];

                continue;
            }

            if ($message['role'] === 'assistant' && ! empty($message['tool_calls'])) {
                $contents[] = [
                    'role' => 'model',
                    'parts' => array_map(fn (array $call) => [
                        'functionCall' => [
                            'id' => $call['id'],
                            'name' => $call['function']['name'],
                            // Same (object) cast and same reason as
                            // functionResponse.response above - a
                            // no-argument tool call (e.g. list_channels())
                            // decodes its '{}' arguments string to an empty
                            // PHP array, which json_encode always renders
                            // as `[]` unless forced back to an object.
                            'args' => (object) (json_decode($call['function']['arguments'], true) ?? []),
                        ],
                    ], $message['tool_calls']),
                ];

                continue;
            }

            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content'] ?? '']],
            ];
        }

        return [$systemInstruction, $contents];
    }
}
