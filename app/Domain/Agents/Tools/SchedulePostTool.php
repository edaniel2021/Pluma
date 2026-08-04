<?php

namespace App\Domain\Agents\Tools;

use App\Domain\Agents\Contracts\AgentToolContract;
use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Models\AgentMessage;
use App\Domain\Agents\Models\AgentThread;
use App\Domain\Organization\Models\Organization;
use App\Domain\Posts\Actions\CreatePost;
use App\Domain\Posts\Enums\PostState;
use App\Models\User;
use Carbon\Carbon;
use Throwable;

class SchedulePostTool implements AgentToolContract
{
    public function __construct(private readonly CreatePost $createPost) {}

    public function name(): string
    {
        return 'schedule_post';
    }

    public function description(): string
    {
        return "Creates a post for one of the organization's connected channels: as a draft (not scheduled), or queued to publish at a future date/time. Always confirm the channel, content, and time with the user before calling this.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'integration_id' => [
                    'type' => 'integer',
                    'description' => 'The id of the channel to post to, from the connected channels list in your instructions.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The plain-text content of the post.',
                ],
                'media_id' => [
                    'type' => 'integer',
                    'description' => 'Optional media library id (from generate_image) to attach. If omitted and an image was generated earlier in this conversation, that image is attached automatically.',
                ],
                'state' => [
                    'type' => 'string',
                    'enum' => ['draft', 'queue'],
                    'description' => "'draft' saves it without scheduling; 'queue' schedules it for scheduled_at.",
                ],
                'scheduled_at' => [
                    'type' => 'string',
                    'description' => 'Required when state is "queue" - a plain local date-time in the organization\'s own timezone, e.g. "2026-08-04 15:00". Do not convert it to UTC yourself - the system does that automatically using the organization\'s timezone setting.',
                ],
            ],
            'required' => ['integration_id', 'content', 'state'],
        ];
    }

    public function handle(array $arguments, Organization $organization, User $user, AgentThread $thread): array
    {
        $integration = $organization->integrations->firstWhere('id', $arguments['integration_id'] ?? null);

        if (! $integration) {
            return ['error' => 'Unknown integration_id - it must match one of the organization\'s connected channels.'];
        }

        $state = PostState::tryFrom($arguments['state'] ?? '') ?? PostState::Draft;

        if ($state === PostState::Queue && empty($arguments['scheduled_at'])) {
            return ['error' => 'scheduled_at is required when state is "queue".'];
        }

        $scheduledAt = null;

        if ($state === PostState::Queue) {
            try {
                // The model gives a plain local date-time in the org's own
                // timezone (not UTC) - it has no reliable way to know the
                // organization's UTC offset itself, and asking it to do that
                // math produced real, hard-to-spot errors in production
                // (a "3 PM Kuwait time" request silently stored as 3 PM
                // UTC). Converting here mirrors Calendar::reschedule()'s
                // existing pattern for the same underlying problem.
                $scheduledAt = Carbon::parse($arguments['scheduled_at'], $organization->timezone)->setTimezone('UTC');
            } catch (Throwable) {
                return ['error' => 'Could not understand scheduled_at - please provide a date/time like "2026-08-04 15:00".'];
            }
        }

        $post = $this->createPost->execute([
            'user_id' => $user->id,
            'integration_id' => $integration->id,
            'content' => (string) ($arguments['content'] ?? ''),
            'state' => $state->value,
            'scheduled_at' => $scheduledAt,
        ]);

        $mediaId = $arguments['media_id'] ?? $this->defaultMediaId($thread);

        if ($mediaId) {
            $media = $organization->getMedia('library')->firstWhere('id', $mediaId);

            if ($media) {
                $post->addMedia($media->getPath())->preservingOriginal()->toMediaCollection('default');
            }
        }

        return [
            'post_id' => $post->id,
            'state' => $post->state->value,
        ];
    }

    /**
     * Falls back to the most recently generated image in this thread when
     * the model doesn't explicitly pass media_id - a real production
     * symptom was posts scheduled with no image at all, despite the model
     * having just generated one a couple of tool calls earlier in the same
     * turn. Same "don't rely solely on the model correctly threading
     * context forward" reasoning behind inlining the connected-channels
     * list into the system prompt instead of a tool call.
     */
    private function defaultMediaId(AgentThread $thread): ?int
    {
        return $thread->messages()
            ->where('role', AgentMessageRole::Tool)
            ->where('tool_name', 'generate_image')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AgentMessage $message) => json_decode($message->content, true)['media_id'] ?? null)
            ->first(fn (?int $mediaId) => $mediaId !== null);
    }
}
