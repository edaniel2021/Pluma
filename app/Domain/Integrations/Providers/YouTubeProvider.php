<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\AbstractSocialProvider;
use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Google's default YouTube API quota is ~10,000 units/day, and a single
 * video upload costs ~1,600 units - about 6 uploads/day/project. That's a
 * real constraint to size multi-tenant rollout around (request a quota
 * increase from Google before onboarding many orgs), not something this
 * code can work around.
 */
class YouTubeProvider extends AbstractSocialProvider
{
    public function key(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    public function socialiteDriver(): string
    {
        // Not 'google' - see the Socialite::extend('youtube', ...) call in
        // AppServiceProvider for why this needs to be a distinct driver.
        return 'youtube';
    }

    public function scopes(): array
    {
        return ['https://www.googleapis.com/auth/youtube.upload'];
    }

    /**
     * Google only returns a refresh_token on the *first* consent, unless
     * you force re-consent - without these, reconnecting would silently
     * stop yielding a refresh_token on subsequent connects.
     *
     * @return array<string, string>
     */
    public function redirectParameters(): array
    {
        return [
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
    }

    public function connect(SocialiteUser $socialiteUser): Integration
    {
        $channel = Http::withToken($socialiteUser->token)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'mine' => 'true',
            ])
            ->json('items.0');

        return Integration::updateOrCreate(
            [
                'provider' => $this->key(),
                'account_id' => $socialiteUser->getId(),
            ],
            [
                'account_name' => $channel['snippet']['title'] ?? $socialiteUser->getName(),
                'avatar_url' => $channel['snippet']['thumbnails']['default']['url'] ?? $socialiteUser->getAvatar(),
                'access_token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken,
                'token_expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
                'disabled_at' => null,
            ]
        );
    }

    public function refreshToken(Integration $integration): void
    {
        if (! $integration->refresh_token) {
            $integration->forceFill(['disabled_at' => now()])->save();

            return;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $integration->refresh_token,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        if ($response->failed()) {
            $integration->forceFill(['disabled_at' => now()])->save();

            return;
        }

        $integration->forceFill([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();
    }

    /**
     * Video-only - YouTube has no text/image post type. Uses the resumable
     * upload protocol's session-initiation step, then a single PUT with
     * the full file (valid resumable-protocol usage for smaller files;
     * true chunked upload for very large videos is a further step not
     * built here).
     */
    public function post(Integration $integration, Post $post): void
    {
        $media = $post->getFirstMedia('default');

        if (! $media || ! str($media->mime_type)->startsWith('video/')) {
            throw new BadBodyException("YouTube requires an attached video - post #{$post->id} has none.");
        }

        $session = $this->request($integration)
            ->withHeaders([
                'X-Upload-Content-Type' => $media->mime_type,
                'X-Upload-Content-Length' => (string) $media->size,
            ])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?'.http_build_query([
                'part' => 'snippet,status',
            ]), [
                'snippet' => [
                    'title' => (string) str($post->content)->limit(100) ?: 'Untitled',
                    'description' => $post->content,
                ],
                'status' => [
                    'privacyStatus' => 'public',
                ],
            ]);

        $this->assertSuccessful($session, $integration);

        $uploadUrl = $session->header('Location');

        if (! $uploadUrl) {
            throw new BadBodyException("YouTube accepted the upload session but returned no upload URL for post #{$post->id}.");
        }

        $upload = Http::withBody(file_get_contents($media->getPath()), $media->mime_type)
            ->put($uploadUrl);

        $this->assertSuccessful($upload, $integration);

        if (! $upload->json('id')) {
            throw new BadBodyException("YouTube accepted the video but returned no video ID for post #{$post->id}.");
        }
    }
}
