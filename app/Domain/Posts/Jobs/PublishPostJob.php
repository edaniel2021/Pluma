<?php

namespace App\Domain\Posts\Jobs;

use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Support\FakeSocialPublisher;
use App\Domain\Posts\Support\PublishingFailedException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * The direct replacement for Postiz's Temporal autopost activity. Deliberately
 * takes just a post ID (not the model) so a fresh copy is always fetched at
 * execution time - in-flight jobs then survive code deploys/queue restarts
 * rather than carrying a stale serialized snapshot.
 *
 * Publishes via FakeSocialPublisher for now; once the integrations phase
 * exists, `handle()` will resolve a real per-Integration provider instead,
 * but the retry/backoff/locking/error-recording shape here shouldn't need
 * to change.
 */
class PublishPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public int $postId)
    {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600];
    }

    /**
     * Guards against the due-post poller double-dispatching for the same
     * post (e.g. a slow job still running when the next minute's poll
     * fires) - the direct equivalent of Temporal's workflow-ID dedup.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->postId))->releaseAfter(120)->expireAfter(180)];
    }

    public function handle(FakeSocialPublisher $publisher): void
    {
        $post = Post::withoutGlobalScope('organization')->find($this->postId);

        // Already handled by a previous attempt, deleted, or no longer
        // actually queued (e.g. the user edited it back to Draft) - a
        // no-op, not a failure.
        if (! $post || $post->state !== PostState::Queue) {
            return;
        }

        CurrentOrganization::set($post->organization);

        try {
            $publisher->publish($post);

            $post->update([
                'state' => PostState::Published,
                'published_at' => now(),
            ]);
        } catch (PublishingFailedException $e) {
            $post->errors()->create([
                'type' => 'platform_error',
                'message' => $e->getMessage(),
                'retry_count' => $this->attempts(),
            ]);

            throw $e;
        } finally {
            CurrentOrganization::clear();
        }
    }

    /**
     * Called once after all retry attempts are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $post = Post::withoutGlobalScope('organization')->find($this->postId);

        if (! $post) {
            return;
        }

        CurrentOrganization::set($post->organization);

        $post->update(['state' => PostState::Error]);

        $post->errors()->create([
            'type' => 'platform_error',
            'message' => 'Publishing failed permanently after '.$this->tries.' attempts: '
                .($exception?->getMessage() ?? 'unknown error'),
            'retry_count' => $this->tries,
        ]);

        CurrentOrganization::clear();
    }
}
