<?php

namespace App\Domain\Agents\Tools;

use App\Domain\Agents\Contracts\AgentToolContract;
use App\Domain\Agents\Models\AgentThread;
use App\Domain\Agents\Support\FalService;
use App\Domain\Agents\Support\GeminiService;
use App\Domain\Agents\Support\OpenAiService;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

class GenerateImageTool implements AgentToolContract
{
    public function __construct(
        private readonly FalService $fal,
        private readonly GeminiService $gemini,
        private readonly OpenAiService $openAi,
    ) {}

    public function name(): string
    {
        return 'generate_image';
    }

    public function description(): string
    {
        return "Generates an image from a text prompt and adds it to the organization's media library. Returns a media_id that can be passed to schedule_post's media_id parameter.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'A detailed description of the image to generate.',
                ],
            ],
            'required' => ['prompt'],
        ];
    }

    public function handle(array $arguments, Organization $organization, User $user, AgentThread $thread): array
    {
        $prompt = (string) ($arguments['prompt'] ?? '');

        if ($prompt === '') {
            return ['error' => 'prompt is required.'];
        }

        // FAL first if configured (generally cheaper/faster), then Gemini
        // if that's the active chat provider (avoids also needing an
        // OpenAI/FAL key in an otherwise Gemini-only setup), then OpenAI's
        // Images API as the final fallback.
        $fileAdder = match (true) {
            $this->fal->isConfigured() => $organization->addMediaFromUrl($this->fal->generateImage($prompt)),
            config('agents.chat_provider') === 'gemini' => $organization->addMediaFromBase64($this->gemini->generateImage($prompt)),
            default => $organization->addMediaFromBase64($this->openAi->generateImage($prompt)),
        };

        // Truncated, not the raw prompt: media.name is a varchar(255)
        // column, but nothing bounds how long a model-generated prompt can
        // be - a verbose one (models tend to write full descriptive
        // paragraphs) overflows it and fails the whole insert.
        $media = $fileAdder->usingName(Str::limit($prompt, 200))->toMediaCollection('library');

        return [
            'media_id' => $media->id,
            'url' => $media->getUrl(),
        ];
    }
}
