<?php

namespace App\Domain\Agents\Tools;

use App\Domain\Agents\Contracts\AgentToolContract;
use App\Domain\Agents\Support\FalService;
use App\Domain\Agents\Support\OpenAiService;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

class GenerateImageTool implements AgentToolContract
{
    public function __construct(
        private readonly FalService $fal,
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

    public function handle(array $arguments, Organization $organization, User $user): array
    {
        $prompt = (string) ($arguments['prompt'] ?? '');

        if ($prompt === '') {
            return ['error' => 'prompt is required.'];
        }

        $fileAdder = $this->fal->isConfigured()
            ? $organization->addMediaFromUrl($this->fal->generateImage($prompt))
            : $organization->addMediaFromBase64($this->openAi->generateImage($prompt));

        $media = $fileAdder->usingName($prompt)->toMediaCollection('library');

        return [
            'media_id' => $media->id,
            'url' => $media->getUrl(),
        ];
    }
}
