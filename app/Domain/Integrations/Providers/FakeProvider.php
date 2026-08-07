<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\AbstractSocialProvider;
use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\Log;

/**
 * Stands in for a real platform until credentials for one are configured -
 * carried over from Phase 3's FakeSocialPublisher, now just another entry
 * in the provider registry instead of being hardcoded into PublishPostJob.
 * Useful for local dev/tests without needing real API keys, and as the
 * reference implementation new providers can be checked against.
 *
 * Deterministically "fails" when a post's content contains the literal
 * marker `[FAIL]`, so retry/backoff behavior can be tested without relying
 * on randomness.
 */
class FakeProvider extends AbstractSocialProvider
{
    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake (local dev)';
    }

    public function socialiteDriver(): string
    {
        return 'fake';
    }

    public function scopes(): array
    {
        return [];
    }

    public function refreshToken(Integration $integration): void
    {
        $integration->forceFill([
            'token_expires_at' => now()->addDays(60),
        ])->save();
    }

    public function post(Integration $integration, Post $post): ?string
    {
        if (str_contains($post->content, '[FAIL]')) {
            throw new BadBodyException("Simulated failure publishing post #{$post->id}.");
        }

        Log::info("Fake-published post #{$post->id} to integration #{$integration->id}: \"{$post->content}\"");

        return "fake-post-{$post->id}";
    }
}
