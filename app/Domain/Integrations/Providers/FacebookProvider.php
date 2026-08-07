<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\AbstractSocialProvider;
use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Facebook Login only surfaces *Pages* the user manages (not their personal
 * profile - the Graph API has no personal-profile posting surface for
 * third-party apps), and one login can manage several Pages, so connect()
 * returns a Collection instead of a single Integration - see
 * SocialProviderContract for why that's allowed.
 *
 * Requires Meta App Review for pages_manage_posts in production; works
 * against your own test Pages in Development Mode without review.
 */
class FacebookProvider extends AbstractSocialProvider
{
    /**
     * A single lightweight GET - made explicit rather than relying on
     * Laravel's implicit 30s default, matching this codebase's established
     * "every HTTP call gets a real, checkable timeout" convention.
     */
    private const ENGAGEMENT_REQUEST_TIMEOUT_SECONDS = 15;

    public function key(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return 'Facebook';
    }

    public function socialiteDriver(): string
    {
        return 'facebook';
    }

    public function scopes(): array
    {
        return ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'];
    }

    /**
     * @return Collection<int, Integration>
     */
    public function connect(SocialiteUser $socialiteUser): Collection
    {
        $longLivedUserToken = $this->exchangeForLongLivedToken($socialiteUser->token);

        $pages = Http::acceptJson()
            ->get('https://graph.facebook.com/v23.0/me/accounts', [
                'fields' => 'id,name,access_token,picture',
                'access_token' => $longLivedUserToken,
            ])
            ->json('data', []);

        return collect($pages)->map(function (array $page) {
            return Integration::updateOrCreate(
                [
                    'provider' => $this->key(),
                    'account_id' => $page['id'],
                ],
                [
                    'account_name' => $page['name'],
                    'avatar_url' => $page['picture']['data']['url'] ?? null,
                    // The Page's own token (not the user's) - this is what
                    // posting to the Page's feed requires.
                    'access_token' => $page['access_token'],
                    'refresh_token' => null,
                    'token_expires_at' => null,
                    'disabled_at' => null,
                ]
            );
        });
    }

    /**
     * Page access tokens derived from a long-lived user token don't expire
     * (barring revoked permissions), so this one extra call up front avoids
     * needing a refresh cycle at all for Facebook.
     */
    protected function exchangeForLongLivedToken(string $shortLivedToken): string
    {
        $response = Http::acceptJson()->get('https://graph.facebook.com/v23.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook.client_id'),
            'client_secret' => config('services.facebook.client_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        return $response->json('access_token', $shortLivedToken);
    }

    public function refreshToken(Integration $integration): void
    {
        // Page tokens derived from a long-lived user token don't expire on
        // their own - if the platform ever rejects one, the user needs to
        // reconnect (which re-derives a fresh Page token), not refresh.
        $integration->forceFill(['disabled_at' => now()])->save();
    }

    public function post(Integration $integration, Post $post): ?string
    {
        $media = $post->getFirstMedia('default');

        $response = $media && str($media->mime_type)->startsWith('image/')
            ? $this->postPhoto($integration, $post, $media->getUrl())
            : $this->postFeedMessage($integration, $post);

        $this->assertSuccessful($response, $integration);

        // /photos returns "post_id" (the feed post) alongside "id" (the
        // photo itself) - "post_id" is the one engagement queries need.
        $postId = $response->json('post_id') ?? $response->json('id');

        if (! $postId) {
            throw new BadBodyException("Facebook accepted the request but returned no post ID for post #{$post->id}.");
        }

        return $postId;
    }

    /**
     * Verified against Meta's Graph API reference: pages_read_engagement
     * (already requested in scopes() above) is sufficient - unlike
     * LinkedIn's equivalent, this isn't gated behind an invite-only
     * permission. "shares" is a struct with a "count" key, but Facebook
     * omits fields entirely when their value would be zero - defaulted to
     * 0 rather than left null, since "zero shares" is a real known value,
     * not "we don't know."
     */
    public function fetchEngagement(Integration $integration, Post $post): array
    {
        if (! $post->provider_post_id) {
            return ['supported' => true, 'likes' => null, 'comments' => null, 'shares' => null];
        }

        $response = $this->request($integration)
            ->timeout(self::ENGAGEMENT_REQUEST_TIMEOUT_SECONDS)
            ->get("https://graph.facebook.com/v23.0/{$post->provider_post_id}", [
                'fields' => 'likes.summary(true),comments.summary(true),shares',
                'access_token' => $integration->access_token,
            ]);

        $this->assertSuccessful($response, $integration);

        return [
            'supported' => true,
            'likes' => $response->json('likes.summary.total_count', 0),
            'comments' => $response->json('comments.summary.total_count', 0),
            'shares' => $response->json('shares.count', 0),
        ];
    }

    protected function postFeedMessage(Integration $integration, Post $post)
    {
        return $this->request($integration)->asForm()->post("https://graph.facebook.com/v23.0/{$integration->account_id}/feed", [
            'message' => $post->content,
            'access_token' => $integration->access_token,
        ]);
    }

    protected function postPhoto(Integration $integration, Post $post, string $imageUrl)
    {
        return $this->request($integration)->asForm()->post("https://graph.facebook.com/v23.0/{$integration->account_id}/photos", [
            'url' => $imageUrl,
            'caption' => $post->content,
            'access_token' => $integration->access_token,
        ]);
    }
}
