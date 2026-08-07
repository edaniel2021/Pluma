<?php

namespace App\Domain\Integrations\Contracts;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Collection;
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
     * Extra provider-specific query params for the redirect step - e.g.
     * Google's access_type/prompt to force a refresh_token to be issued.
     * Empty for most providers.
     *
     * @return array<string, string>
     */
    public function redirectParameters(): array;

    /**
     * Create or update the Integration record(s) for a freshly connected
     * account (called from the OAuth callback). Most providers connect
     * exactly one account per OAuth round-trip; Facebook/Instagram can
     * discover several (every Page the user manages, or every Page's
     * linked IG Business Account) from a single login, so they return a
     * Collection instead.
     *
     * @return Integration|Collection<int, Integration>
     */
    public function connect(SocialiteUser $socialiteUser): Integration|Collection;

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
     * Publish the post to this integration's connected account. Returns
     * the platform's own identifier for the created post (e.g. Facebook's
     * post ID, LinkedIn's share URN from the x-restli-id header) so it can
     * be persisted onto Post::provider_post_id for later lookups (e.g.
     * fetchEngagement()) - null if the platform genuinely exposes no such
     * identifier.
     *
     * @throws \App\Domain\Integrations\Support\RefreshTokenException when the access token was rejected
     * @throws \App\Domain\Integrations\Support\BadBodyException on any other platform-side failure
     */
    public function post(Integration $integration, Post $post): ?string;

    /**
     * Fetch the current likes/comments/shares snapshot for an already-
     * published post. Most platforms don't support this yet (see
     * AbstractSocialProvider's default, which returns `supported: false`
     * rather than throwing - "not supported" is an expected, normal
     * result for most providers today, not an error condition).
     *
     * @return array{supported: bool, likes: ?int, comments: ?int, shares: ?int}
     *
     * @throws \App\Domain\Integrations\Support\RefreshTokenException when the access token was rejected
     * @throws \App\Domain\Integrations\Support\BadBodyException on any other platform-side failure
     */
    public function fetchEngagement(Integration $integration, Post $post): array;
}
