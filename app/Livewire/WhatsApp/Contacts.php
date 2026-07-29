<?php

namespace App\Livewire\WhatsApp;

use App\Domain\WhatsApp\Models\WhatsAppAccount;
use App\Domain\WhatsApp\Models\WhatsAppContact;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Contacts extends Component
{
    public WhatsAppAccount $account;

    public string $phone_number = '';

    public string $name = '';

    public function mount(WhatsAppAccount $account): void
    {
        $this->account = $account;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'phone_number' => [
                'required',
                'string',
                Rule::unique('whatsapp_contacts', 'phone_number')->where('whatsapp_account_id', $this->account->id),
            ],
            'name' => ['nullable', 'string'],
        ];
    }

    public function addContact(): void
    {
        $validated = $this->validate();

        $this->account->contacts()->create($validated + ['opted_in_at' => now()]);

        $this->reset(['phone_number', 'name']);
    }

    public function removeContact(WhatsAppContact $contact): void
    {
        $contact->delete();
    }

    public function render()
    {
        return view('livewire.whatsapp.contacts', [
            'contacts' => $this->account->contacts()->latest()->get(),
        ]);
    }
}
