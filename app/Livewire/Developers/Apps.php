<?php

namespace App\Livewire\Developers;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use Livewire\Component;

/**
 * Pluma's equivalent of Postiz's OAuthApp management screen - lets a user
 * register a third-party OAuth app (a Passport Client) that can request
 * access to their organization's data via the standard authorization-code
 * grant, and revoke apps they no longer trust.
 */
class Apps extends Component
{
    public string $name = '';

    public string $redirect_url = '';

    public bool $confidential = true;

    public ?string $newClientId = null;

    public ?string $newClientSecret = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'redirect_url' => ['required', 'url'],
        ];
    }

    public function register(ClientRepository $clients): void
    {
        $validated = $this->validate();

        $client = $clients->createAuthorizationCodeGrantClient(
            $validated['name'],
            [$validated['redirect_url']],
            $this->confidential,
            Auth::user(),
        );

        $this->newClientId = $client->id;
        $this->newClientSecret = $client->plainSecret;

        $this->reset(['name', 'redirect_url']);
        $this->confidential = true;
    }

    /**
     * Not a route-bound Client param: oauth_clients has no tenancy scope of
     * its own (it's owned by a User, not an Organization), so ownership is
     * checked explicitly here rather than relying on implicit binding.
     */
    public function revoke(string $clientId): void
    {
        Auth::user()->oauthApps()->findOrFail($clientId)->delete();
    }

    public function dismissNewClient(): void
    {
        $this->reset(['newClientId', 'newClientSecret']);
    }

    public function render()
    {
        return view('livewire.developers.apps', [
            'clients' => Auth::user()->oauthApps()->latest()->get(),
        ]);
    }
}
