<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header nav is grouped into categories (Social, Communications, SEO,
 * Analytics & Reports) with the rest built out in later phases - these
 * placeholder pages exist so the new nav categories aren't dead links
 * before each phase ships.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_communications_placeholder_page_loads(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('communications.index'))
            ->assertOk()
            ->assertSee('Coming soon');
    }

    public function test_seo_placeholder_page_loads(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('seo.index'))
            ->assertOk()
            ->assertSee('Coming soon');
    }

    public function test_analytics_placeholder_page_loads(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertSee('Coming soon');
    }

    public function test_social_nav_group_lists_all_of_its_pages(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Launches')
            ->assertSee('Posts')
            ->assertSee('Media')
            ->assertSee('Integrations')
            ->assertSee('WhatsApp')
            ->assertSee('AI Assistant')
            ->assertSee('Communications')
            ->assertSee('SEO')
            ->assertSee('Analytics &amp; Reports', false);
    }
}
