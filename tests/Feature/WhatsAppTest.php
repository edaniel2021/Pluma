<?php

namespace Tests\Feature;

use App\Domain\WhatsApp\Actions\SendWhatsAppBroadcast;
use App\Domain\WhatsApp\Jobs\SendWhatsAppBroadcastJob;
use App\Domain\WhatsApp\Models\WhatsAppAccount;
use App\Domain\WhatsApp\Models\WhatsAppContact;
use App\Livewire\WhatsApp\Accounts;
use App\Livewire\WhatsApp\Broadcasts;
use App\Livewire\WhatsApp\Contacts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_can_be_connected_via_manual_entry(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        Livewire::test(Accounts::class)
            ->set('waba_id', '1234567890')
            ->set('phone_number_id', '9876543210')
            ->set('display_phone_number', '+15550001111')
            ->set('access_token', 'permanent-token')
            ->call('connect');

        $account = WhatsAppAccount::first();

        $this->assertNotNull($account);
        $this->assertSame('1234567890', $account->waba_id);
        $this->assertSame($user->currentTeam->id, $account->organization_id);
    }

    public function test_disconnecting_removes_the_account(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $account = WhatsAppAccount::factory()->create();

        Livewire::test(Accounts::class)
            ->call('disconnect', $account->id);

        $this->assertNull($account->fresh());
    }

    public function test_organizations_only_see_their_own_whatsapp_accounts(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $this->actingAs($userA);
        WhatsAppAccount::factory()->create(['display_phone_number' => '+15550001111']);

        $userB = User::factory()->withPersonalTeam()->create();
        $this->actingAs($userB);
        WhatsAppAccount::factory()->create(['display_phone_number' => '+15550002222']);

        $this->actingAs($userA);

        Livewire::test(Accounts::class)
            ->assertSee('+15550001111')
            ->assertDontSee('+15550002222');
    }

    public function test_a_contact_can_be_added_and_removed(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $account = WhatsAppAccount::factory()->create();

        Livewire::test(Contacts::class, ['account' => $account])
            ->set('phone_number', '+15551234567')
            ->set('name', 'Jane Doe')
            ->call('addContact');

        $contact = $account->contacts()->first();
        $this->assertNotNull($contact);
        $this->assertSame('Jane Doe', $contact->name);

        Livewire::test(Contacts::class, ['account' => $account])
            ->call('removeContact', $contact->id);

        $this->assertNull($contact->fresh());
    }

    public function test_duplicate_phone_numbers_are_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $account = WhatsAppAccount::factory()->create();
        $account->contacts()->create(['phone_number' => '+15551234567', 'opted_in_at' => now()]);

        Livewire::test(Contacts::class, ['account' => $account])
            ->set('phone_number', '+15551234567')
            ->call('addContact')
            ->assertHasErrors(['phone_number']);
    }

    public function test_sending_a_broadcast_creates_pending_recipients_and_dispatches_the_job(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $account = WhatsAppAccount::factory()->create();
        $contact = WhatsAppContact::factory()->create(['whatsapp_account_id' => $account->id]);

        Livewire::test(Broadcasts::class, ['account' => $account])
            ->set('template_name', 'hello_world')
            ->set('template_language', 'en_US')
            ->set('contact_ids', [$contact->id])
            ->call('send');

        $broadcast = $account->broadcasts()->first();

        $this->assertNotNull($broadcast);
        $this->assertSame('hello_world', $broadcast->template_name);
        $this->assertSame(1, $broadcast->recipients()->where('status', 'pending')->count());

        Queue::assertPushed(SendWhatsAppBroadcastJob::class, fn ($job) => $job->broadcastId === $broadcast->id);
    }

    public function test_the_send_action_records_per_recipient_success_and_failure(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $account = WhatsAppAccount::factory()->create();
        $goodContact = WhatsAppContact::factory()->create(['whatsapp_account_id' => $account->id, 'phone_number' => '+15550001111']);
        $badContact = WhatsAppContact::factory()->create(['whatsapp_account_id' => $account->id, 'phone_number' => '+15550002222']);

        $broadcast = $account->broadcasts()->create([
            'template_name' => 'hello_world',
            'template_language' => 'en_US',
            'status' => 'sending',
        ]);

        $goodRecipient = $broadcast->recipients()->create(['whatsapp_contact_id' => $goodContact->id, 'status' => 'pending']);
        $badRecipient = $broadcast->recipients()->create(['whatsapp_contact_id' => $badContact->id, 'status' => 'pending']);

        Http::fake([
            'graph.facebook.com/*' => function ($request) {
                return str($request['to'])->contains('1111')
                    ? Http::response(['messages' => [['id' => 'wamid.123']]], 200)
                    : Http::response(['error' => ['message' => 'Recipient not in allowed list']], 400);
            },
        ]);

        (new SendWhatsAppBroadcast)->execute($broadcast);

        $this->assertSame('sent', $goodRecipient->fresh()->status);
        $this->assertSame('wamid.123', $goodRecipient->fresh()->message_id);
        $this->assertSame('failed', $badRecipient->fresh()->status);
        $this->assertNotNull($badRecipient->fresh()->error_message);
        $this->assertSame('failed', $broadcast->fresh()->status);
    }
}
