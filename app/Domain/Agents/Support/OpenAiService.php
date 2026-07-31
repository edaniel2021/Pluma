<?php

namespace App\Domain\Agents\Support;

use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

/**
 * Thin wrapper over openai-php/laravel - the conversation loop and tools
 * call these two methods rather than the OpenAI facade directly, so the
 * model/config choices live in one place.
 */
class OpenAiService
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function chat(array $messages, array $tools = []): CreateResponse
    {
        return OpenAI::chat()->create(array_filter([
            'model' => config('agents.model'),
            'messages' => $messages,
            'tools' => $tools ?: null,
        ]));
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
