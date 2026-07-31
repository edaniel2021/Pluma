<?php

namespace Tests\Feature;

use App\Domain\Posts\Models\Post;
use App\Livewire\Admin\Errors;
use App\Livewire\Admin\Stats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_is_forbidden_from_the_admin_panel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $this->get(route('admin.stats'))->assertForbidden();
        $this->get(route('admin.errors'))->assertForbidden();
    }

    public function test_a_platform_admin_can_view_the_admin_panel(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['is_platform_admin' => true]);
        $this->actingAs($user);

        $this->get(route('admin.stats'))->assertOk();
        $this->get(route('admin.errors'))->assertOk();
    }

    public function test_the_errors_list_shows_errors_across_all_organizations(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_platform_admin' => true]);

        $userA = User::factory()->withPersonalTeam()->create();
        $postA = Post::factory()->for($userA->currentTeam, 'organization')->create();
        $postA->errors()->create(['type' => 'token_expired', 'message' => 'Org A token expired', 'retry_count' => 1]);

        $userB = User::factory()->withPersonalTeam()->create();
        $postB = Post::factory()->for($userB->currentTeam, 'organization')->create();
        $postB->errors()->create(['type' => 'platform_error', 'message' => 'Org B platform error', 'retry_count' => 2]);

        $this->actingAs($admin);

        Livewire::test(Errors::class)
            ->assertSee('Org A token expired')
            ->assertSee('Org B platform error')
            ->assertSee($userA->currentTeam->name)
            ->assertSee($userB->currentTeam->name);
    }

    public function test_the_errors_list_can_be_filtered_by_type(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_platform_admin' => true]);
        $user = User::factory()->withPersonalTeam()->create();
        $post = Post::factory()->for($user->currentTeam, 'organization')->create();
        $post->errors()->create(['type' => 'token_expired', 'message' => 'Expired token error', 'retry_count' => 1]);
        $post->errors()->create(['type' => 'platform_error', 'message' => 'Platform failure error', 'retry_count' => 1]);

        $this->actingAs($admin);

        Livewire::test(Errors::class)
            ->set('type', 'token_expired')
            ->assertSee('Expired token error')
            ->assertDontSee('Platform failure error');
    }

    public function test_stats_reports_correct_cross_organization_counts(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_platform_admin' => true]);
        $userA = User::factory()->withPersonalTeam()->create();
        Post::factory()->for($userA->currentTeam, 'organization')->create(['state' => 'draft']);
        $userB = User::factory()->withPersonalTeam()->create();
        Post::factory()->for($userB->currentTeam, 'organization')->create(['state' => 'draft']);

        $this->actingAs($admin);

        Livewire::test(Stats::class)
            ->assertViewHas('organizationCount', fn ($count) => $count >= 3)
            ->assertViewHas('postsByState', fn ($byState) => $byState->get('Draft') === 2);
    }
}
