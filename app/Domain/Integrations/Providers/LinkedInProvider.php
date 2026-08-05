<?php

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\AbstractSocialProvider;
use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\Http;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
        $media = $post->getFirstMedia('default');

        // LinkedIn requires an already-uploaded image's urn referenced in
        // content.media.id - unlike Facebook's /photos endpoint (which
        // just takes a public image URL), there's no way to hand it a URL
        // directly. Non-image attachments (e.g. video) are silently
        // dropped for now, same as commentary-only posts - LinkedIn's
        // video-upload flow isn't built yet.
        $content = $media && str($media->mime_type)->startsWith('image/')
            ? ['media' => ['id' => $this->uploadImage($integration, $media)]]
            : null;

        $response = $this->request($integration)
            ->withHeaders($this->versionedHeaders())
            ->post('https://api.linkedin.com/rest/posts', array_filter([
                'author' => "urn:li:person:{$integration->account_id}",
                'commentary' => $post->content,
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'content' => $content,
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ]));

        $this->assertSuccessful($response, $integration);

        if (! $response->header('x-restli-id')) {
            throw new BadBodyException("LinkedIn accepted the request but returned no post ID for post #{$post->id}.");
        }
    }

    /**
     * LinkedIn's Images API: register the upload to get a one-time upload
     * URL + the image's own urn, PUT the raw bytes to that URL, then hand
     * the urn (not the URL) to /rest/posts. Mirrors YouTubeProvider's
     * register-then-PUT-the-file shape, but the upload PUT itself needs
     * the Bearer token here (unlike Google's resumable session URLs,
     * which are already pre-authorized once initiated).
     */
    protected function uploadImage(Integration $integration, Media $media): string
    {
        $registration = $this->request($integration)
            ->withHeaders($this->versionedHeaders())
            ->post('https://api.linkedin.com/rest/images?action=initializeUpload', [
                'initializeUploadRequest' => [
                    'owner' => "urn:li:person:{$integration->account_id}",
                ],
            ]);

        $this->assertSuccessful($registration, $integration);

        $uploadUrl = $registration->json('value.uploadUrl');
        $imageUrn = $registration->json('value.image');

        if (! $uploadUrl || ! $imageUrn) {
            throw new BadBodyException("LinkedIn accepted the image upload registration but returned no uploadUrl/image urn for integration #{$integration->id}.");
        }

        $upload = Http::withToken($integration->access_token)
            ->withBody(file_get_contents($media->getPath()), $media->mime_type)
            ->put($uploadUrl);

        $this->assertSuccessful($upload, $integration);

        return $imageUrn;
    }

    /**
     * @return array<string, string>
     */
    protected function versionedHeaders(): array
    {
        // Not the current calendar month - LinkedIn ships a new version
        // monthly but doesn't activate it on day 1 of that month (confirmed
        // live: on 2026-08-03, "202608" 426'd with NONEXISTENT_VERSION
        // while 202607 was still the latest active version). One month
        // behind is always safely within the ~12-month support window and
        // past any rollout lag. Shared by both /rest/posts and the Images
        // API - both are versioned the same way.
        return [
            'LinkedIn-Version' => now()->subMonth()->format('Ym'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }
}
