<?php

namespace Tests\Feature;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\BadBodyException;
use App\Domain\Integrations\Support\SocialProviderManager;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Jobs\PublishPostJob;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishPostJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_a_due_post(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $integration = Integration::factory()->create();

        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'content' => 'A perfectly normal post.',
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        (new PublishPostJob($post->id))->handle(app(SocialProviderManager::class));

        $post->refresh();

        $this->assertSame(PostState::Published, $post->state);
        $this->assertNotNull($post->published_at);
        $this->assertSame(0, $post->errors()->count());
    }

    public function test_a_publishing_failure_records_an_error_and_rethrows(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $integration = Integration::factory()->create();

        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'content' => 'This one is rigged to fail. [FAIL]',
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->expectException(BadBodyException::class);

        try {
            (new PublishPostJob($post->id))->handle(app(SocialProviderManager::class));
        } finally {
            $post->refresh();

            // Still Queue, not Error yet - failed() (exhausted retries) is
            // what moves it to Error, not an individual failed attempt.
            $this->assertSame(PostState::Queue, $post->state);
            $this->assertSame(1, $post->errors()->count());
            $this->assertSame('platform_error', $post->errors()->first()->type);
        }
    }

    public function test_exhausting_retries_marks_the_post_as_errored(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $integration = Integration::factory()->create();

        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'content' => 'Doomed. [FAIL]',
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new PublishPostJob($post->id);
        $job->failed(new BadBodyException('Simulated final failure.'));

        $post->refresh();

        $this->assertSame(PostState::Error, $post->state);
        $this->assertSame(1, $post->errors()->count());
    }

    public function test_it_is_a_no_op_if_the_post_is_no_longer_queued(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $integration = Integration::factory()->create();

        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'state' => PostState::Draft,
            'scheduled_at' => now()->subMinute(),
        ]);

        (new PublishPostJob($post->id))->handle(app(SocialProviderManager::class));

        $post->refresh();

        $this->assertSame(PostState::Draft, $post->state);
        $this->assertNull($post->published_at);
    }

    public function test_it_is_a_no_op_if_the_post_has_no_integration(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        (new PublishPostJob($post->id))->handle(app(SocialProviderManager::class));

        $post->refresh();

        $this->assertSame(PostState::Queue, $post->state);
        $this->assertNull($post->published_at);
    }
}
