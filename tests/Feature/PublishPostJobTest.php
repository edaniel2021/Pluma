<?php

namespace Tests\Feature;

use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Jobs\PublishPostJob;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Support\FakeSocialPublisher;
use App\Domain\Posts\Support\PublishingFailedException;
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

        $post = Post::factory()->create([
            'content' => 'A perfectly normal post.',
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        (new PublishPostJob($post->id))->handle(new FakeSocialPublisher);

        $post->refresh();

        $this->assertSame(PostState::Published, $post->state);
        $this->assertNotNull($post->published_at);
        $this->assertSame(0, $post->errors()->count());
    }

    public function test_a_publishing_failure_records_an_error_and_rethrows(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'content' => 'This one is rigged to fail. [FAIL]',
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->expectException(PublishingFailedException::class);

        try {
            (new PublishPostJob($post->id))->handle(new FakeSocialPublisher);
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

        $post = Post::factory()->create([
            'content' => 'Doomed. [FAIL]',
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new PublishPostJob($post->id);
        $job->failed(new PublishingFailedException('Simulated final failure.'));

        $post->refresh();

        $this->assertSame(PostState::Error, $post->state);
        $this->assertSame(1, $post->errors()->count());
    }

    public function test_it_is_a_no_op_if_the_post_is_no_longer_queued(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $post = Post::factory()->create([
            'state' => PostState::Draft,
            'scheduled_at' => now()->subMinute(),
        ]);

        (new PublishPostJob($post->id))->handle(new FakeSocialPublisher);

        $post->refresh();

        $this->assertSame(PostState::Draft, $post->state);
        $this->assertNull($post->published_at);
    }
}
