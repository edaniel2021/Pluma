<?php

namespace App\Domain\Integrations\Support;

use App\Domain\Integrations\Contracts\SocialProviderContract;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Posts\Models\Post;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\User as SocialiteUser;

abstract class AbstractSocialProvider implements SocialProviderContract
{
    public function connect(SocialiteUser $socialiteUser): Integration|Collection
    {
        return Integration::updateOrCreate(
            [
                'provider' => $this->key(),
                'account_id' => $socialiteUser->getId(),
            ],
            [
                'account_name' => $socialiteUser->getNickname() ?: $socialiteUser->getName(),
                'avatar_url' => $socialiteUser->getAvatar(),
                'access_token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken,
                'token_expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
                'disabled_at' => null,
            ]
        );
    }

    public function checkValidity(Integration $integration): bool
    {
        return ! $integration->isDisabled() && ! $integration->needsTokenRefresh();
    }

    public function redirectParameters(): array
    {
        return [];
    }

    /**
     * Default for every provider that hasn't implemented real engagement
     * fetching yet - `supported: false` is a normal, expected result here,
     * not an error, so this returns cleanly rather than throwing.
     *
     * @return array{supported: bool, likes: ?int, comments: ?int, shares: ?int}
     */
    public function fetchEngagement(Integration $integration, Post $post): array
    {
        return ['supported' => false, 'likes' => null, 'comments' => null, 'shares' => null];
    }

    /**
     * Shared authenticated request builder for provider API calls.
     */
    protected function request(Integration $integration): PendingRequest
    {
        return Http::withToken($integration->access_token)->acceptJson();
    }

    /**
     * Normalizes a failed platform response into our typed exceptions -
     * mirrors Postiz's own RefreshTokenException/BadBodyException split.
     */
    protected function assertSuccessful(Response $response, Integration $integration): Response
    {
        if ($response->status() === 401) {
            throw new RefreshTokenException("Access token rejected for integration #{$integration->id}.");
        }

        if ($response->failed()) {
            throw new BadBodyException("Request failed for integration #{$integration->id}: {$response->body()}");
        }

        return $response;
    }
}
