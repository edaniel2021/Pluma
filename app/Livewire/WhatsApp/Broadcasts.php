<?php

namespace App\Livewire\WhatsApp;

use App\Domain\WhatsApp\Jobs\SendWhatsAppBroadcastJob;
use App\Domain\WhatsApp\Models\WhatsAppAccount;
use Livewire\Component;

class Broadcasts extends Component
{
    public WhatsAppAccount $account;

    public string $template_name = '';

    public string $template_language = 'en_US';

    /**
     * @var array<int, int>
     */
    public array $contact_ids = [];

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
            'template_name' => ['required', 'string'],
            'template_language' => ['required', 'string'],
            'contact_ids' => ['required', 'array', 'min:1'],
        ];
    }

    public function send(): void
    {
        $validated = $this->validate();

        $broadcast = $this->account->broadcasts()->create([
            'template_name' => $validated['template_name'],
            'template_language' => $validated['template_language'],
            'status' => 'sending',
        ]);

        foreach ($validated['contact_ids'] as $contactId) {
            $broadcast->recipients()->create([
                'whatsapp_contact_id' => $contactId,
                'status' => 'pending',
            ]);
        }

        SendWhatsAppBroadcastJob::dispatch($broadcast->id);

        $this->reset(['template_name', 'contact_ids']);
    }

    public function render()
    {
        return view('livewire.whatsapp.broadcasts', [
            'contacts' => $this->account->contacts()->orderBy('name')->get(),
            'broadcasts' => $this->account->broadcasts()->latest()->with('recipients')->get(),
        ]);
    }
}
