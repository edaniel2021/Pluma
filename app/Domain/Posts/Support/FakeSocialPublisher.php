<?php

namespace App\Domain\Posts\Support;

use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\Log;

/**
 * Stands in for a real social-platform provider (Instagram/Facebook/etc.)
 * until the integrations phase exists. The goal of Phase 3 is to prove the
 * queue/scheduling mechanics - PublishPostJob shouldn't need to change when
 * this is later replaced by a real SocialProviderManager that resolves a
 * provider per Integration.
 *
 * Deterministically "fails" when a post's content contains the literal
 * marker `[FAIL]`, so retry/backoff behavior can be tested without relying
 * on randomness.
 */
class FakeSocialPublisher
{
    public function publish(Post $post): void
    {
        if (str_contains($post->content, '[FAIL]')) {
            throw new PublishingFailedException("Simulated failure publishing post #{$post->id}.");
        }

        Log::info("Fake-published post #{$post->id}: \"{$post->content}\"");
    }
}
