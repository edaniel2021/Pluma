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
 * There's no separate "Instagram Login" for this - Instagram content
 * publishing rides on the same Facebook Login + Graph API as
 * FacebookProvider, just requesting Instagram-specific scopes and reading
 * each Page's linked `instagram_business_account` instead of the Page
 * itself. An Instagram post is authenticated with the *Page's* access
 * token, not a separate Instagram one.
 *
 * Requires Meta App Review for instagram_content_publish in production.
 * Two-step container-then-publish pattern per post, and Instagram has no
 * text-only post type - every post needs an attached image (video/Reels
 * support is a further step not built here yet).
 */
class InstagramProvider extends AbstractSocialProvider
{
    public function key(): string
    {
        return 'instagram';
    }

    public function label(): string
    {
        return 'Instagram';
    }

    public function socialiteDriver(): string
    {
        // Not 'facebook' - see the Socialite::extend('instagram-facebook', ...)
        // call in AppServiceProvider for why this needs to be a distinct
        // driver (same Meta app as Facebook, but its own redirect URI).
        return 'instagram-facebook';
    }

    public function scopes(): array
    {
        return ['pages_show_list', 'instagram_basic', 'instagram_content_publish'];
    }

    /**
     * @return Collection<int, Integration>
     */
    public function connect(SocialiteUser $socialiteUser): Collection
    {
        $longLivedUserToken = $this->exchangeForLongLivedToken($socialiteUser->token);

        $pages = Http::acceptJson()
            ->get('https://graph.facebook.com/v23.0/me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account{id,username,profile_picture_url}',
                'access_token' => $longLivedUserToken,
            ])
            ->json('data', []);

        return collect($pages)
            ->filter(fn (array $page) => isset($page['instagram_business_account']))
            ->map(function (array $page) {
                $ig = $page['instagram_business_account'];

                return Integration::updateOrCreate(
                    [
                        'provider' => $this->key(),
                        'account_id' => $ig['id'],
                    ],
                    [
                        'account_name' => $ig['username'] ?? $page['name'],
                        'avatar_url' => $ig['profile_picture_url'] ?? null,
                        // Instagram Graph API calls are authenticated with
                        // the linked Page's token, not a separate IG one.
                        'access_token' => $page['access_token'],
                        'refresh_token' => null,
                        'token_expires_at' => null,
                        'disabled_at' => null,
                    ]
                );
            })
            ->values();
    }

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
        // Same as Facebook: the underlying Page token doesn't expire on its
        // own. If the platform ever rejects one, the user needs to
        // reconnect rather than refresh.
        $integration->forceFill(['disabled_at' => now()])->save();
    }

    public function post(Integration $integration, Post $post): void
    {
        $media = $post->getFirstMedia('default');

        if (! $media || ! str($media->mime_type)->startsWith('image/')) {
            throw new BadBodyException("Instagram requires an attached image - post #{$post->id} has none.");
        }

        $container = $this->assertSuccessful(
            $this->request($integration)->asForm()->post("https://graph.facebook.com/v23.0/{$integration->account_id}/media", [
                'image_url' => $media->getUrl(),
                'caption' => $post->content,
                'access_token' => $integration->access_token,
            ]),
            $integration
        );

        $containerId = $container->json('id');

        if (! $containerId) {
            throw new BadBodyException("Instagram accepted the media container but returned no ID for post #{$post->id}.");
        }

        $publish = $this->assertSuccessful(
            $this->request($integration)->asForm()->post("https://graph.facebook.com/v23.0/{$integration->account_id}/media_publish", [
                'creation_id' => $containerId,
                'access_token' => $integration->access_token,
            ]),
            $integration
        );

        if (! $publish->json('id')) {
            throw new BadBodyException("Instagram accepted the publish request but returned no post ID for post #{$post->id}.");
        }
    }
}
