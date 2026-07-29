<?php

namespace Tests\Feature;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use App\Livewire\Launches\Calendar;
use App\Livewire\Launches\Composer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LaunchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // MediaLibrary writes to the real disk otherwise, polluting local dev storage.
        Storage::fake(config('media-library.disk_name'));
    }

    public function test_a_launch_can_be_composed_against_a_connected_integration(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();

        Livewire::test(Composer::class)
            ->set('integration_id', $integration->id)
            ->set('content', 'Hello from the composer.')
            ->set('state', PostState::Queue->value)
            ->set('scheduled_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertRedirect(route('launches.index'));

        $post = Post::first();

        $this->assertNotNull($post);
        $this->assertSame($integration->id, $post->integration_id);
        $this->assertSame(PostState::Queue, $post->state);
    }

    public function test_composing_without_selecting_an_integration_fails_validation(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        Livewire::test(Composer::class)
            ->set('content', 'Missing a target account.')
            ->set('integration_id', null)
            ->call('save')
            ->assertHasErrors(['integration_id' => 'required']);
    }

    public function test_the_composer_loads_an_existing_launch_for_editing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();
        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'content' => 'Original.',
        ]);

        Livewire::test(Composer::class, ['post' => $post])
            ->assertSet('content', 'Original.')
            ->set('content', 'Edited.')
            ->call('save');

        $this->assertSame('Edited.', $post->fresh()->content);
    }

    public function test_deleting_a_launch_from_the_composer_removes_it(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();
        $post = Post::factory()->create(['integration_id' => $integration->id]);

        Livewire::test(Composer::class, ['post' => $post])
            ->call('delete');

        $this->assertNull($post->fresh());
    }

    public function test_the_calendar_only_shows_posts_with_an_integration_and_a_schedule(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();

        $scheduledLaunch = Post::factory()->create([
            'integration_id' => $integration->id,
            'scheduled_at' => now()->addDay(),
            'content' => 'A real launch',
        ]);

        // Not on the calendar: no integration attached.
        Post::factory()->create([
            'scheduled_at' => now()->addDay(),
        ]);

        Livewire::test(Calendar::class)
            ->assertSee('A real launch');
    }

    public function test_rescheduling_via_the_calendar_updates_the_post(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();
        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'state' => PostState::Queue,
            'scheduled_at' => now()->addDay(),
        ]);

        $newDateTime = now()->addDays(3)->startOfMinute();

        Livewire::test(Calendar::class)
            ->call('reschedule', $post->id, $newDateTime->toIso8601String());

        $post->refresh();

        $this->assertTrue($newDateTime->equalTo($post->scheduled_at));
    }

    public function test_rescheduling_a_published_post_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();
        $post = Post::factory()->create([
            'integration_id' => $integration->id,
            'state' => PostState::Published,
            'scheduled_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);

        $originalScheduledAt = $post->scheduled_at;

        Livewire::test(Calendar::class)
            ->call('reschedule', $post->id, now()->addDays(3)->toIso8601String());

        $this->assertTrue($originalScheduledAt->equalTo($post->fresh()->scheduled_at));
    }

    public function test_an_image_can_be_attached_when_composing_a_launch(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();

        Livewire::test(Composer::class)
            ->set('integration_id', $integration->id)
            ->set('content', 'A launch with a picture.')
            ->set('upload', UploadedFile::fake()->image('launch.jpg'))
            ->call('save');

        $post = Post::first();

        $this->assertNotNull($post);
        $this->assertSame(1, $post->getMedia('default')->count());
        $this->assertSame('launch.jpg', $post->getFirstMedia('default')->file_name);
    }

    public function test_removing_the_attachment_clears_it(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();
        $post = Post::factory()->create(['integration_id' => $integration->id]);
        $post->addMedia(UploadedFile::fake()->image('launch.jpg'))->toMediaCollection('default');

        Livewire::test(Composer::class, ['post' => $post])
            ->call('removeMedia');

        $this->assertSame(0, $post->fresh()->getMedia('default')->count());
    }
}
