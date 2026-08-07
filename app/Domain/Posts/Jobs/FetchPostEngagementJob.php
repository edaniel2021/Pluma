<?php

namespace App\Domain\Posts\Jobs;

use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Integrations\Support\RefreshTokenException;
use App\Domain\Integrations\Support\SocialProviderManager;
use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Posts\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dispatched by posts:refresh-engagement for every eligible post - just one
 * cheap HTTP call per post (currently only FacebookProvider::fetchEngagement()
 * does real work; every other provider's default returns instantly with no
 * network call at all, see AbstractSocialProvider). Deliberately tries=1, no
 * backoff - the scheduled command re-dispatches for the same posts every
 * cycle anyway, so a failed attempt just gets retried on the next run rather
 * than needing its own retry logic.
 */
class FetchPostEngagementJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $postId)
    {
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->postId))->releaseAfter(30)->expireAfter(60)];
    }

    public function handle(SocialProviderManager $providers): void
    {
        $post = Post::withoutGlobalScope('organization')->with('integration')->find($this->postId);

        if (! $post || ! $post->integration) {
            return;
        }

        CurrentOrganization::set($post->organization);

        try {
            $result = $providers->driver($post->integration->provider)->fetchEngagement($post->integration, $post);

            if ($result['supported']) {
                $post->update([
                    'likes_count' => $result['likes'],
                    'comments_count' => $result['comments'],
                    'shares_count' => $result['shares'],
                    'engagement_fetched_at' => now(),
                    'engagement_fetch_error' => null,
                ]);
            }
        } catch (RefreshTokenException|BadBodyException $e) {
            $post->update(['engagement_fetch_error' => $e->getMessage()]);
        } finally {
            CurrentOrganization::clear();
        }
    }

    /**
     * Only reached if handle() itself throws something other than the two
     * exception types already caught above (e.g. a genuinely unexpected
     * error) - tries=1 means this is the only failure hook that fires.
     */
    public function failed(?Throwable $exception): void
    {
        $post = Post::withoutGlobalScope('organization')->find($this->postId);

        if (! $post) {
            return;
        }

        CurrentOrganization::set($post->organization);
        $post->update(['engagement_fetch_error' => $exception?->getMessage() ?? 'Unknown error']);
        CurrentOrganization::clear();
    }
}
