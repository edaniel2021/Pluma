<?php

namespace Tests\Feature;

use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use App\Livewire\Posts\Form;
use App\Livewire\Posts\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PostCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_post_can_be_created_and_is_scoped_to_the_current_organization(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)->test(Form::class)
            ->set('content', 'Hello from the test suite.')
            ->set('state', PostState::Draft->value)
            ->call('save')
            ->assertRedirect(route('posts.index'));

        $post = Post::first();

        $this->assertNotNull($post);
        $this->assertSame('Hello from the test suite.', $post->content);
        $this->assertSame($user->currentTeam->id, $post->organization_id);
        $this->assertSame($user->id, $post->user_id);
    }

    public function test_a_post_can_be_updated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $post = Post::factory()->create(['content' => 'Original content']);

        Livewire::actingAs($user)->test(Form::class, ['post' => $post])
            ->set('content', 'Updated content')
            ->call('save');

        $this->assertSame('Updated content', $post->fresh()->content);
    }

    public function test_a_post_can_be_deleted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $post = Post::factory()->create();

        Livewire::actingAs($user)->test(Index::class)
            ->call('delete', $post->id);

        $this->assertNull($post->fresh());
    }

    public function test_organizations_only_see_their_own_posts(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();

        $this->actingAs($userA);
        Post::factory()->create(['content' => 'Post for org A']);

        $this->actingAs($userB);
        Post::factory()->create(['content' => 'Post for org B']);

        $this->actingAs($userA);
        $this->assertSame(1, Post::count());
        $this->assertSame('Post for org A', Post::first()->content);

        $this->actingAs($userB);
        $this->assertSame(1, Post::count());
        $this->assertSame('Post for org B', Post::first()->content);
    }
}
