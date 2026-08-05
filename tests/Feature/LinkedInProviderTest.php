<?php

namespace Tests\Feature;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Providers\LinkedInProvider;
use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Real production complaint: "yesterday I posted on LinkedIn, but only the
 * text came, no image." LinkedInProvider::post() never looked at the
 * post's attached media at all - it always sent a plain commentary-only
 * body, unlike Facebook/Instagram/YouTube which already handle attachments.
 * Fixed via LinkedIn's Images API (register upload -> PUT the bytes ->
 * reference the returned urn in content.media.id).
 */
class LinkedInProviderTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user, Integration $integration): Post
    {
        CurrentOrganization::set($user->currentTeam);
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'integration_id' => $integration->id,
            'content' => 'Check out this sleek wheelchair for just 100KD!',
        ]);
        CurrentOrganization::clear();

        return $post;
    }

    public function test_posting_without_an_image_sends_plain_text_only(): void
    {
        Http::fake([
            'api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:12345']),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $integration = Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'linkedin',
            'account_id' => '999',
        ]);
        $post = $this->makePost($user, $integration);

        app(LinkedInProvider::class)->post($integration, $post);

        Http::assertSent(function ($request) {
            return str($request->url())->contains('rest/posts')
                && ! array_key_exists('content', $request->data());
        });
    }

    public function test_posting_with_an_attached_image_uploads_it_and_references_the_urn(): void
    {
        Http::fake([
            'api.linkedin.com/rest/images*' => Http::response([
                'value' => [
                    'uploadUrl' => 'https://upload.linkedin.com/fake-upload-url',
                    'image' => 'urn:li:image:FAKE123',
                ],
            ], 200),
            'upload.linkedin.com/*' => Http::response('', 201),
            'api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:12345']),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $integration = Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'linkedin',
            'account_id' => '999',
        ]);
        $post = $this->makePost($user, $integration);
        // A real (if tiny) 1x1 PNG, not an arbitrary string - LinkedInProvider
        // decides whether to upload based on the media's sniffed mime type,
        // which only detects actual image bytes as image/png.
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $post->addMediaFromBase64(base64_encode($pngBytes))
            ->usingName('wheelchair')
            ->toMediaCollection('default');

        app(LinkedInProvider::class)->post($integration, $post->fresh());

        Http::assertSent(fn ($request) => str($request->url())->contains('rest/images')
            && $request->data()['initializeUploadRequest']['owner'] === 'urn:li:person:999');

        Http::assertSent(fn ($request) => str($request->url())->contains('upload.linkedin.com')
            && $request->body() === $pngBytes);

        Http::assertSent(function ($request) {
            return str($request->url())->contains('rest/posts')
                && ($request->data()['content']['media']['id'] ?? null) === 'urn:li:image:FAKE123';
        });
    }
}
