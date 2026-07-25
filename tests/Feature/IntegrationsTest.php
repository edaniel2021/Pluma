<?php

namespace Tests\Feature;

use App\Domain\Integrations\Models\Integration;
use App\Livewire\Integrations\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_the_current_organizations_integrations(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $this->actingAs($userA);
        $integrationA = Integration::factory()->create(['account_name' => 'Org A Account']);

        $userB = User::factory()->withPersonalTeam()->create();
        $this->actingAs($userB);
        Integration::factory()->create(['account_name' => 'Org B Account']);

        $this->actingAs($userA);

        Livewire::test(Index::class)
            ->assertSee('Org A Account')
            ->assertDontSee('Org B Account');
    }

    public function test_the_fake_provider_is_not_offered_as_a_connect_option(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        Livewire::test(Index::class)
            ->assertDontSee('Connect Fake');
    }

    public function test_disconnecting_deletes_the_integration(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $integration = Integration::factory()->create();

        Livewire::test(Index::class)
            ->call('disconnect', $integration->id);

        $this->assertNull($integration->fresh());
    }

    public function test_connecting_to_an_unregistered_provider_404s(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $this->get('/integrations/not-a-real-provider/redirect')->assertNotFound();
    }

    public function test_connecting_to_the_fake_provider_404s(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $this->get('/integrations/fake/redirect')->assertNotFound();
    }
}
