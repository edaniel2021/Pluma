<?php

namespace Tests\Feature;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Providers\FacebookProvider;
use App\Domain\Integrations\Providers\LinkedInProvider;
use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookProviderTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user, Integration $integration, array $attributes = []): Post
    {
        CurrentOrganization::set($user->currentTeam);
        $post = Post::factory()->create(array_merge([
            'user_id' => $user->id,
            'integration_id' => $integration->id,
            'content' => 'Check out this sleek wheelchair for just 100KD!',
        ], $attributes));
        CurrentOrganization::clear();

        return $post;
    }

    public function test_posting_a_feed_message_returns_the_post_id(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response(['id' => '123_456'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $integration = Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'facebook',
            'account_id' => '123',
        ]);
        $post = $this->makePost($user, $integration);

        $postId = app(FacebookProvider::class)->post($integration, $post);

        $this->assertSame('123_456', $postId);
    }

    /**
     * /photos returns "post_id" (the feed post) alongside "id" (the photo
     * itself) - the feed post ID is the one engagement queries need, not
     * the photo's own ID.
     */
    public function test_posting_a_photo_returns_the_post_id_not_the_photo_id(): void
    {
        Http::fake([
            'graph.facebook.com/*/photos' => Http::response(['id' => 'photo-999', 'post_id' => '123_456'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $integration = Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'facebook',
            'account_id' => '123',
        ]);
        $post = $this->makePost($user, $integration);
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $post->addMediaFromBase64(base64_encode($pngBytes))->usingName('wheelchair')->toMediaCollection('default');

        $postId = app(FacebookProvider::class)->post($integration, $post->fresh());

        $this->assertSame('123_456', $postId);
    }

    public function test_post_throws_when_the_platform_returns_no_id_at_all(): void
    {
        Http::fake([
            'graph.facebook.com/*/feed' => Http::response([], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $integration = Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'facebook',
            'account_id' => '123',
        ]);
        $post = $this->makePost($user, $integration);

        $this->expectException(BadBodyException::class);

        app(FacebookProvider::class)->post($integration, $post);
    }

    /**
     * pages_read_engagement is already requested in FacebookProvider::scopes()
     * - unlike LinkedIn's engagement read (gated behind the invite-only
     * r_member_social_feed permission), this needs no new permission.
     */
    public function test_fetch_engagement_returns_likes_comments_and_shares(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'likes' => ['summary' => ['total_count' => 42]],
                'comments' => ['summary' => ['total_count' => 7]],
                'shares' => ['count' => 3],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $integration = Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'facebook',
        ]);
        $post = $this->makePost($user, $integration, ['provider_post_id' => '123_456']);

        $result = app(FacebookProvider::class)->fetchEngagement($integration, $post);

        $this->assertTrue($result['supported']);
        $this->assertSame(42, $result['likes']);
        $this->assertSame(7, $result['comments']);
        $this->assertSame(3, $result['shares']);
    }

    /**
     * Facebook omits fields entirely when their value would be zero - a
     * post with zero shares (or, for a fresh post, sometimes zero likes/
     * comments too) simply lacks the key rather than returning 0
     * explicitly. Must default to 0, not null - "zero shares" is a real
     * known value, not "we don't know."
     */
    public function test_fetch_engagement_defaults_missing_fields_to_zero(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '123_456'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $integration = Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'facebook',
        ]);
        $post = $this->makePost($user, $integration, ['provider_post_id' => '123_456']);

        $result = app(FacebookProvider::class)->fetchEngagement($integration, $post);

        $this->assertSame(0, $result['likes']);
        $this->assertSame(0, $result['comments']);
        $this->assertSame(0, $result['shares']);
    }

    /**
     * Every provider that hasn't implemented real engagement fetching
     * gets AbstractSocialProvider's default - "not supported" is a
     * normal, expected result, not an error.
     */
    public function test_a_provider_without_engagement_support_returns_the_default(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $integration = Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'linkedin',
        ]);
        $post = $this->makePost($user, $integration);

        $result = app(LinkedInProvider::class)->fetchEngagement($integration, $post);

        $this->assertFalse($result['supported']);
        $this->assertNull($result['likes']);
        $this->assertNull($result['comments']);
        $this->assertNull($result['shares']);
    }
}
