<?php

namespace App\Livewire\WhatsApp;

use App\Domain\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Connecting is manual entry (WABA ID / Phone Number ID / permanent access
 * token), not an OAuth redirect - see the migration docblock for why.
 */
class Accounts extends Component
{
    public string $waba_id = '';

    public string $phone_number_id = '';

    public string $display_phone_number = '';

    public string $access_token = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'waba_id' => ['required', 'string'],
            'phone_number_id' => ['required', 'string'],
            'display_phone_number' => ['nullable', 'string'],
            'access_token' => ['required', 'string'],
        ];
    }

    public function connect(): void
    {
        $validated = $this->validate();

        WhatsAppAccount::create($validated + ['connected_at' => now()]);

        $this->reset(['waba_id', 'phone_number_id', 'display_phone_number', 'access_token']);
    }

    public function disconnect(WhatsAppAccount $account): void
    {
        $account->delete();
    }

    public function render()
    {
        return view('livewire.whatsapp.accounts', [
            'accounts' => Auth::user()->currentTeam->whatsAppAccounts,
        ]);
    }
}
