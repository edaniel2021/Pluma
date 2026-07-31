<?php

namespace Tests\Feature;

use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Events\AgentMessageCreated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgentBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_agent_message_dispatches_the_broadcast_event(): void
    {
        Event::fake([AgentMessageCreated::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $thread = $user->currentTeam->agentThreads()->create(['user_id' => $user->id]);
        $message = $thread->messages()->create(['role' => AgentMessageRole::User, 'content' => 'hi']);

        Event::assertDispatched(AgentMessageCreated::class, fn ($event) => $event->message->is($message));
    }

    public function test_the_broadcast_event_targets_the_threads_own_private_channel(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $thread = $user->currentTeam->agentThreads()->create(['user_id' => $user->id]);
        $message = $thread->messages()->create(['role' => AgentMessageRole::User, 'content' => 'hi']);

        $event = new AgentMessageCreated($message);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-agent-thread.'.$thread->id, $channels[0]->name);
        $this->assertSame('AgentMessageCreated', $event->broadcastAs());
        $this->assertSame(['id' => $message->id], $event->broadcastWith());
    }

    /**
     * phpunit.xml sets BROADCAST_CONNECTION=null for the test environment
     * (sensible, so tests don't need a real Reverb server) - but
     * NullBroadcaster::auth() is a total no-op that never consults
     * routes/channels.php at all, so a real HTTP hit against it would
     * always return an empty 200 regardless of authorization logic.
     * 'reverb' resolves to Illuminate's PusherBroadcaster, whose
     * verifyUserCanAccessChannel() genuinely runs the registered callback -
     * and that check is pure PHP, no actual network call to Reverb needed.
     *
     * Switching config('broadcasting.default') alone isn't enough, though:
     * Broadcast::channel() calls (routes/channels.php) register on whatever
     * driver INSTANCE was already resolved as the default at boot time
     * (BroadcastManager::__call() resolves and caches `driver()` per name),
     * so a config change after boot doesn't retroactively move those
     * registrations onto a freshly-created 'reverb' instance. Re-requiring
     * channels.php after the switch re-runs those Broadcast::channel()
     * calls against the now-current default, registering them for real.
     */
    private function useRealBroadcastAuthorization(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
    }

    public function test_channel_authorization_allows_the_threads_own_organization(): void
    {
        $this->useRealBroadcastAuthorization();

        $user = User::factory()->withPersonalTeam()->create();
        $thread = $user->currentTeam->agentThreads()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-agent-thread.'.$thread->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk();
    }

    public function test_channel_authorization_denies_other_organizations(): void
    {
        $this->useRealBroadcastAuthorization();

        $owner = User::factory()->withPersonalTeam()->create();
        $thread = $owner->currentTeam->agentThreads()->create(['user_id' => $owner->id]);

        $intruder = User::factory()->withPersonalTeam()->create();

        $this->actingAs($intruder)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-agent-thread.'.$thread->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }
}
