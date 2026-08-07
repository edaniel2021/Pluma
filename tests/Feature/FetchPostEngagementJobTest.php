<?php

namespace Tests\Feature;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\SocialProviderManager;
use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Jobs\FetchPostEngagementJob;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchPostEngagementJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_engagement_for_a_supported_provider(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'likes' => ['summary' => ['total_count' => 10]],
                'comments' => ['summary' => ['total_count' => 2]],
                'shares' => ['count' => 1],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $integration = Integration::factory()->create(['provider' => 'facebook']);
        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'state' => PostState::Published,
            'provider_post_id' => '123_456',
        ]);
        CurrentOrganization::clear();

        (new FetchPostEngagementJob($post->id))->handle(app(SocialProviderManager::class));

        $post->refresh();

        $this->assertSame(10, $post->likes_count);
        $this->assertSame(2, $post->comments_count);
        $this->assertSame(1, $post->shares_count);
        $this->assertNotNull($post->engagement_fetched_at);
        $this->assertNull($post->engagement_fetch_error);
    }

    public function test_it_leaves_engagement_untouched_for_an_unsupported_provider(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $integration = Integration::factory()->create(['provider' => 'linkedin']);
        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'state' => PostState::Published,
            'provider_post_id' => 'urn:li:share:12345',
        ]);
        CurrentOrganization::clear();

        (new FetchPostEngagementJob($post->id))->handle(app(SocialProviderManager::class));

        $post->refresh();

        $this->assertNull($post->likes_count);
        $this->assertNull($post->engagement_fetched_at);
    }

    public function test_a_failed_fetch_records_the_error_without_throwing(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $integration = Integration::factory()->create(['provider' => 'facebook']);
        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'state' => PostState::Published,
            'provider_post_id' => '123_456',
        ]);
        CurrentOrganization::clear();

        (new FetchPostEngagementJob($post->id))->handle(app(SocialProviderManager::class));

        $post->refresh();

        $this->assertNull($post->likes_count);
        $this->assertNotNull($post->engagement_fetch_error);
    }

    public function test_it_is_a_no_op_for_a_deleted_post(): void
    {
        (new FetchPostEngagementJob(999999))->handle(app(SocialProviderManager::class));

        $this->assertSame(0, Post::count());
    }
}
