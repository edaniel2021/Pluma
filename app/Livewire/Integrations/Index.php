<?php

namespace App\Livewire\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Support\SocialProviderManager;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function disconnect(Integration $integration): void
    {
        $integration->delete();
    }

    public function render(SocialProviderManager $providers)
    {
        return view('livewire.integrations.index', [
            'integrations' => Auth::user()->currentTeam->integrations()->latest()->get(),
            // 'fake' has no real OAuth credentials, so it isn't offered as
            // a connect option here even though it's a registered provider.
            'connectableProviders' => collect($providers->available())
                ->except('fake')
                ->map(fn ($class) => app($class)),
        ]);
    }
}
