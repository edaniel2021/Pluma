<?php

namespace App\Domain\Integrations\Contracts;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Posts\Models\Post;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * One implementation per platform (config/social-providers.php is the
 * registry) - adding platform #3 is "one class + one config line."
 * Modeled on Postiz's own SocialAbstract/social.integrations.interface.ts
 * pattern, adapted to lean on Socialite for the OAuth dance itself rather
 * than reimplementing it.
 */
interface SocialProviderContract
{
    /**
     * The registry key (config/social-providers.php), e.g. 'linkedin', 'x'.
     */
    public function key(): string;

    /**
     * Human-readable name for the "connect" UI.
     */
    public function label(): string;

    /**
     * The Socialite driver name to use for the OAuth connect flow, e.g.
     * 'linkedin-openid', 'x'.
     */
    public function socialiteDriver(): string;

    /**
     * Extra OAuth scopes needed beyond Socialite's per-driver defaults
     * (typically posting/write scopes the default login-only scopes don't
     * include).
     *
     * @return array<int, string>
     */
    public function scopes(): array;

    /**
     * Create or update the Integration record for a freshly connected
     * account (called from the OAuth callback).
     */
    public function connect(SocialiteUser $socialiteUser): Integration;

    /**
     * Exchange the refresh token for a new access token and persist it.
     * Should mark the integration disabled (not throw) if the platform
     * reports the refresh token itself is no longer valid.
     */
    public function refreshToken(Integration $integration): void;

    /**
     * Cheap local check (not a network call) for whether this integration
     * looks usable right now.
     */
    public function checkValidity(Integration $integration): bool;

    /**
     * Publish the post to this integration's connected account.
     *
     * @throws \App\Domain\Integrations\Support\RefreshTokenException when the access token was rejected
     * @throws \App\Domain\Integrations\Support\BadBodyException on any other platform-side failure
     */
    public function post(Integration $integration, Post $post): void;
}
