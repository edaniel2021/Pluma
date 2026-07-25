<?php

namespace App\Console\Commands;

use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Jobs\PublishPostJob;
use App\Domain\Posts\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The single poller that replaces Temporal's autopost AND missing-post
 * recovery workflows: it doesn't distinguish "on time" from "overdue", so a
 * post that was somehow missed by an earlier run just gets picked up on the
 * next one. Queries across every organization (bypassing the tenancy scope
 * deliberately - this runs with no authenticated user, so there's no
 * "current organization" to filter by), then PublishPostJob resolves each
 * post's own organization when it runs.
 */
#[Signature('posts:dispatch-due')]
#[Description('Dispatch a publish job for every post that is due to go out.')]
class DispatchDuePosts extends Command
{
    public function handle(): int
    {
        $duePosts = Post::withoutGlobalScope('organization')
            ->where('state', PostState::Queue)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($duePosts as $post) {
            PublishPostJob::dispatch($post->id);
        }

        $this->info("Dispatched {$duePosts->count()} due post(s).");

        return self::SUCCESS;
    }
}
