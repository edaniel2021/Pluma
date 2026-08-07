<?php

namespace App\Console\Commands;

use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Jobs\FetchPostEngagementJob;
use App\Domain\Posts\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Mirrors DispatchDuePosts's shape: bypasses the tenancy scope (no
 * authenticated user in a scheduled command), dispatches by post ID (not
 * the model), each job resolves its own organization.
 *
 * Filtered to `provider = facebook` explicitly rather than asking every
 * provider - FacebookProvider is the only one that implements real
 * engagement fetching today (see AbstractSocialProvider's "not supported"
 * default). Add more provider keys here as they gain support; a
 * capability-check contract method isn't worth it yet for one provider.
 *
 * Bounded to posts published in the last 30 days - engagement on older
 * posts changes little, and this keeps the poller's workload from growing
 * unbounded as the posts table grows over time.
 */
#[Signature('posts:refresh-engagement')]
#[Description('Refresh likes/comments/shares for recently published posts on providers that support it.')]
class RefreshPostEngagement extends Command
{
    private const LOOKBACK_DAYS = 30;

    private const SUPPORTED_PROVIDERS = ['facebook'];

    public function handle(): int
    {
        $posts = Post::withoutGlobalScope('organization')
            ->where('state', PostState::Published)
            ->whereNotNull('provider_post_id')
            ->where('published_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->whereHas('integration', fn ($query) => $query->whereIn('provider', self::SUPPORTED_PROVIDERS))
            ->get();

        foreach ($posts as $post) {
            FetchPostEngagementJob::dispatch($post->id);
        }

        $this->info("Dispatched engagement refresh for {$posts->count()} post(s).");

        return self::SUCCESS;
    }
}
