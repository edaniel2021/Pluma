<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\AbstractSocialProvider;
use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\Http;

/**
 * Personal-profile posting only for now - posting as an organization page
 * needs LinkedIn's Marketing Developer Platform partner approval, which is
 * a separate (slower) application process from the base "Sign In with
 * LinkedIn using OpenID Connect" + "Share on LinkedIn" products this uses.
 *
 * LinkedIn has no refresh-token grant on standard 3-legged OAuth access
 * tokens (they're long-lived, ~60 days, and must be re-authorized via the
 * full connect flow when they expire) - refreshToken() disables the
 * integration to prompt reconnect rather than pretending a refresh happened.
 */
class LinkedInProvider extends AbstractSocialProvider
{
    public function key(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return 'LinkedIn';
    }

    public function socialiteDriver(): string
    {
        return 'linkedin-openid';
    }

    public function scopes(): array
    {
        // Beyond linkedin-openid's default ['openid', 'profile', 'email'] -
        // this is the "Share on LinkedIn" product's posting scope, and
        // must be a product your LinkedIn developer app has been granted.
        return ['w_member_social'];
    }

    public function refreshToken(Integration $integration): void
    {
        $integration->forceFill(['disabled_at' => now()])->save();
    }

    public function post(Integration $integration, Post $post): void
    {
        $response = $this->request($integration)
            ->withHeaders([
                'LinkedIn-Version' => now()->format('Ym'),
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->post('https://api.linkedin.com/rest/posts', [
                'author' => "urn:li:person:{$integration->account_id}",
                'commentary' => $post->content,
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ]);

        $this->assertSuccessful($response, $integration);

        if (! $response->header('x-restli-id')) {
            throw new BadBodyException("LinkedIn accepted the request but returned no post ID for post #{$post->id}.");
        }
    }
}
