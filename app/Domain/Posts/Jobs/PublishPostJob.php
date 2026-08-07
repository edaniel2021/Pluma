<?php

namespace App\Domain\Posts\Jobs;

use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Integrations\Support\RefreshTokenException;
use App\Domain\Integrations\Support\SocialProviderManager;
use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
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
 * Resolves the provider via SocialProviderManager per the post's Integration
 * (see App\Domain\Integrations) - this shape (retry/backoff/locking/error-
 * recording) didn't need to change from Phase 3's version against
 * FakeSocialPublisher; only what's inside the try block did.
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

    public function handle(SocialProviderManager $providers): void
    {
        $post = Post::withoutGlobalScope('organization')->with('integration')->find($this->postId);

        // Already handled by a previous attempt, deleted, no longer actually
        // queued (e.g. the user edited it back to Draft), or never had a
        // publishing target attached - a no-op, not a failure.
        if (! $post || $post->state !== PostState::Queue || ! $post->integration) {
            return;
        }

        CurrentOrganization::set($post->organization);

        try {
            $providerPostId = $providers->driver($post->integration->provider)->post($post->integration, $post);

            $post->update([
                'state' => PostState::Published,
                'published_at' => now(),
                'provider_post_id' => $providerPostId,
            ]);
        } catch (RefreshTokenException $e) {
            $post->errors()->create([
                'type' => 'token_expired',
                'message' => $e->getMessage(),
                'retry_count' => $this->attempts(),
            ]);

            throw $e;
        } catch (BadBodyException $e) {
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
            'type' => $exception instanceof RefreshTokenException ? 'token_expired' : 'platform_error',
            'message' => 'Publishing failed permanently after '.$this->tries.' attempts: '
                .($exception?->getMessage() ?? 'unknown error'),
            'retry_count' => $this->tries,
        ]);

        CurrentOrganization::clear();
    }
}
