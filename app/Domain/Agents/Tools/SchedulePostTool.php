<?php

namespace App\Domain\Agents\Tools;

use App\Domain\Agents\Contracts\AgentToolContract;
use App\Domain\Organization\Models\Organization;
use App\Domain\Posts\Actions\CreatePost;
use App\Domain\Posts\Enums\PostState;
use App\Models\User;

class SchedulePostTool implements AgentToolContract
{
    public function __construct(private readonly CreatePost $createPost) {}

    public function name(): string
    {
        return 'schedule_post';
    }

    public function description(): string
    {
        return "Creates a post for one of the organization's connected channels: as a draft (not scheduled), or queued to publish at a future UTC datetime. Always confirm the channel, content, and time with the user before calling this.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'integration_id' => [
                    'type' => 'integer',
                    'description' => 'The id of the channel to post to, from list_channels.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The plain-text content of the post.',
                ],
                'media_id' => [
                    'type' => 'integer',
                    'description' => 'Optional media library id (from generate_image) to attach.',
                ],
                'state' => [
                    'type' => 'string',
                    'enum' => ['draft', 'queue'],
                    'description' => "'draft' saves it without scheduling; 'queue' schedules it for scheduled_at.",
                ],
                'scheduled_at' => [
                    'type' => 'string',
                    'description' => 'Required when state is "queue" - ISO 8601 UTC datetime to publish at.',
                ],
            ],
            'required' => ['integration_id', 'content', 'state'],
        ];
    }

    public function handle(array $arguments, Organization $organization, User $user): array
    {
        $integration = $organization->integrations->firstWhere('id', $arguments['integration_id'] ?? null);

        if (! $integration) {
            return ['error' => 'Unknown integration_id - call list_channels first.'];
        }

        $state = PostState::tryFrom($arguments['state'] ?? '') ?? PostState::Draft;

        if ($state === PostState::Queue && empty($arguments['scheduled_at'])) {
            return ['error' => 'scheduled_at is required when state is "queue".'];
        }

        $post = $this->createPost->execute([
            'user_id' => $user->id,
            'integration_id' => $integration->id,
            'content' => (string) ($arguments['content'] ?? ''),
            'state' => $state->value,
            'scheduled_at' => $arguments['scheduled_at'] ?? null,
        ]);

        if (! empty($arguments['media_id'])) {
            $media = $organization->getMedia('library')->firstWhere('id', $arguments['media_id']);

            if ($media) {
                $post->addMedia($media->getPath())->preservingOriginal()->toMediaCollection('default');
            }
        }

        return [
            'post_id' => $post->id,
            'state' => $post->state->value,
        ];
    }
}
