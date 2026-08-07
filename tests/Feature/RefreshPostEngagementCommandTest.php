<?php

namespace Tests\Feature;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Jobs\FetchPostEngagementJob;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshPostEngagementCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $user, string $provider, array $attributes): Post
    {
        CurrentOrganization::set($user->currentTeam);
        $integration = Integration::factory()->create(['provider' => $provider]);
        $post = Post::factory()->create(array_merge(['integration_id' => $integration->id], $attributes));
        CurrentOrganization::clear();

        return $post;
    }

    public function test_it_dispatches_for_a_recently_published_facebook_post(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $post = $this->makePost($user, 'facebook', [
            'state' => PostState::Published,
            'provider_post_id' => '123_456',
            'published_at' => now()->subDays(5),
        ]);

        $this->artisan('posts:refresh-engagement')->assertSuccessful();

        Queue::assertPushed(FetchPostEngagementJob::class, fn ($job) => $job->postId === $post->id);
    }

    /**
     * Only Facebook implements real engagement fetching today - filtered
     * at the query level rather than dispatching for every provider and
     * relying on the job to no-op, since that would mean an unbounded,
     * ever-growing number of pointless job dispatches (a real, recurring
     * cost) for providers that will never support this.
     */
    public function test_it_does_not_dispatch_for_an_unsupported_provider(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $this->makePost($user, 'linkedin', [
            'state' => PostState::Published,
            'provider_post_id' => 'urn:li:share:12345',
            'published_at' => now()->subDays(5),
        ]);

        $this->artisan('posts:refresh-engagement')->assertSuccessful();

        Queue::assertNotPushed(FetchPostEngagementJob::class);
    }

    public function test_it_does_not_dispatch_for_a_post_published_more_than_30_days_ago(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $this->makePost($user, 'facebook', [
            'state' => PostState::Published,
            'provider_post_id' => '123_456',
            'published_at' => now()->subDays(31),
        ]);

        $this->artisan('posts:refresh-engagement')->assertSuccessful();

        Queue::assertNotPushed(FetchPostEngagementJob::class);
    }

    public function test_it_does_not_dispatch_for_a_post_with_no_provider_post_id(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $this->makePost($user, 'facebook', [
            'state' => PostState::Published,
            'provider_post_id' => null,
            'published_at' => now()->subDays(5),
        ]);

        $this->artisan('posts:refresh-engagement')->assertSuccessful();

        Queue::assertNotPushed(FetchPostEngagementJob::class);
    }
}
