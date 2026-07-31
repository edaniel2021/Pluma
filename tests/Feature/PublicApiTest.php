<?php

namespace Tests\Feature;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Posts\Models\Post;
use App\Livewire\Developers\Apps;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sanctum_token_with_the_right_ability_can_list_and_create_posts(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test', ['read', 'create'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->withToken($token)
            ->postJson('/api/v1/posts', ['content' => 'Hello from the API'])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Hello from the API')
            ->assertJsonPath('data.state', 'draft');

        $this->assertSame(1, Post::count());
    }

    public function test_a_sanctum_token_without_the_right_ability_is_forbidden(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('read-only', ['read'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/posts', ['content' => 'Should not be allowed'])
            ->assertForbidden();

        $this->assertSame(0, Post::count());
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/posts')->assertUnauthorized();
    }

    public function test_organizations_only_see_their_own_data_via_the_api(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        Post::factory()->for($userA->currentTeam, 'organization')->create(['content' => 'Org A post']);

        $userB = User::factory()->withPersonalTeam()->create();
        Post::factory()->for($userB->currentTeam, 'organization')->create(['content' => 'Org B post']);

        $tokenA = $userA->createToken('test', ['read'])->plainTextToken;

        $response = $this->withToken($tokenA)->getJson('/api/v1/posts')->assertOk();

        $contents = collect($response->json('data'))->pluck('content');
        $this->assertTrue($contents->contains('Org A post'));
        $this->assertFalse($contents->contains('Org B post'));
    }

    public function test_a_post_belonging_to_another_organization_404s(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();
        $otherPost = Post::factory()->for($userB->currentTeam, 'organization')->create();

        $tokenA = $userA->createToken('test', ['read'])->plainTextToken;

        $this->withToken($tokenA)
            ->getJson("/api/v1/posts/{$otherPost->id}")
            ->assertNotFound();
    }

    public function test_integrations_are_listed_without_leaking_tokens(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Integration::factory()->create([
            'organization_id' => $user->currentTeam->id,
            'provider' => 'linkedin',
            'access_token' => 'super-secret-token',
        ]);
        $token = $user->createToken('test', ['read'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/integrations')->assertOk();

        $response->assertJsonPath('data.0.provider', 'linkedin');
        $this->assertStringNotContainsString('super-secret-token', $response->getContent());
    }

    public function test_the_api_rate_limiter_blocks_requests_beyond_the_configured_tier_limit(): void
    {
        config(['billing.api_rate_limits.default' => 2]);

        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('test', ['read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/posts')->assertOk();
        $this->withToken($token)->getJson('/api/v1/posts')->assertOk();
        $this->withToken($token)->getJson('/api/v1/posts')->assertStatus(429);
    }

    public function test_a_passport_token_can_access_the_api_with_a_scoped_ability(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Passport::actingAs($user, ['read']);

        $this->getJson('/api/v1/posts')->assertOk();
    }

    public function test_a_passport_token_missing_the_scope_is_forbidden(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Passport::actingAs($user, ['read']);

        $this->postJson('/api/v1/posts', ['content' => 'nope'])->assertForbidden();
    }

    public function test_an_oauth_app_can_be_registered_via_the_developer_apps_ui(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        Livewire::test(Apps::class)
            ->set('name', 'My Integration')
            ->set('redirect_url', 'https://example.com/callback')
            ->call('register');

        $client = Client::first();

        $this->assertNotNull($client);
        $this->assertSame('My Integration', $client->name);
        $this->assertSame($user->id, $client->owner_id);
        $this->assertSame(User::class, $client->owner_type);
        $this->assertSame(['https://example.com/callback'], $client->redirect_uris);
    }

    public function test_a_user_can_only_revoke_their_own_oauth_app(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $client = $owner->oauthApps()->create([
            'name' => 'Owned App',
            'redirect_uris' => ['https://example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'revoked' => false,
        ]);

        $intruder = User::factory()->withPersonalTeam()->create();
        $this->actingAs($intruder);

        try {
            Livewire::test(Apps::class)->call('revoke', $client->id);
            $this->fail('Expected a ModelNotFoundException when revoking another user\'s OAuth app.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // Expected - the intruder's oauthApps() relation doesn't include this client.
        }

        $this->assertNotNull($client->fresh());

        $this->actingAs($owner);

        Livewire::test(Apps::class)->call('revoke', $client->id);

        $this->assertNull($client->fresh());
    }

    /**
     * Drives the real HTTP routes Passport registers (not just
     * Passport::actingAs()'s test shortcut) to prove the full
     * authorization-code grant is wired together correctly end-to-end,
     * mirroring the real browser + curl verification.
     */
    public function test_the_full_authorization_code_grant_issues_a_working_access_token(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $client = app(\Laravel\Passport\ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Full Flow App',
            ['https://example.com/callback'],
            true,
            $user,
        );

        $this->actingAs($user);

        $authorizeResponse = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://example.com/callback',
            'response_type' => 'code',
            'scope' => 'read',
        ]))->assertOk();

        $authorizeResponse->assertSee('Full Flow App');

        $authToken = session('authToken');
        $this->assertNotNull($authToken);

        $approveResponse = $this->post('/oauth/authorize', [
            'client_id' => $client->id,
            'auth_token' => $authToken,
        ])->assertRedirect();

        $redirectUrl = $approveResponse->headers->get('Location');
        $this->assertStringStartsWith('https://example.com/callback?code=', $redirectUrl);
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);

        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'redirect_uri' => 'https://example.com/callback',
            'code' => $query['code'],
        ])->assertOk();

        $accessToken = $tokenResponse->json('access_token');
        $this->assertNotNull($accessToken);

        $this->withToken($accessToken)
            ->getJson('/api/v1/posts')
            ->assertOk();
    }
}
