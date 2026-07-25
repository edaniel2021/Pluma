<?php

namespace Tests\Feature;

use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Jobs\PublishPostJob;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchDuePostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_dispatches_due_queued_posts_across_every_organization(): void
    {
        Queue::fake();

        $userA = User::factory()->withPersonalTeam()->create();
        $this->actingAs($userA);
        $duePostOrgA = Post::factory()->create([
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        $userB = User::factory()->withPersonalTeam()->create();
        $this->actingAs($userB);
        $duePostOrgB = Post::factory()->create([
            'state' => PostState::Queue,
            'scheduled_at' => now()->subMinute(),
        ]);

        // Should NOT be dispatched: scheduled in the future.
        $futurePost = Post::factory()->create([
            'state' => PostState::Queue,
            'scheduled_at' => now()->addHour(),
        ]);

        // Should NOT be dispatched: not in the Queue state.
        $draftPost = Post::factory()->create([
            'state' => PostState::Draft,
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->artisan('posts:dispatch-due')->assertSuccessful();

        Queue::assertPushed(PublishPostJob::class, 2);
        Queue::assertPushed(fn (PublishPostJob $job) => $job->postId === $duePostOrgA->id);
        Queue::assertPushed(fn (PublishPostJob $job) => $job->postId === $duePostOrgB->id);
        Queue::assertNotPushed(fn (PublishPostJob $job) => $job->postId === $futurePost->id);
        Queue::assertNotPushed(fn (PublishPostJob $job) => $job->postId === $draftPost->id);
    }
}
