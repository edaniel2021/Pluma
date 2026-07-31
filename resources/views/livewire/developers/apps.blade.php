<div>
    <x-form-section submit="register">
        <x-slot name="title">
            {{ __('Register an OAuth App') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Third-party apps can request access to your organization\'s data via OAuth. Register an app to get a client ID and secret.') }}
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 sm:col-span-4">
                <x-label for="name" value="{{ __('App Name') }}" />
                <x-input id="name" type="text" class="mt-1 block w-full" wire:model="name" />
                <x-input-error for="name" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <x-label for="redirect_url" value="{{ __('Redirect URL') }}" />
                <x-input id="redirect_url" type="text" class="mt-1 block w-full" wire:model="redirect_url" placeholder="https://example.com/oauth/callback" />
                <x-input-error for="redirect_url" class="mt-2" />
            </div>

            <div class="col-span-6">
                <label class="flex items-center">
                    <x-checkbox wire:model="confidential" />
                    <span class="ms-2 text-sm text-gray-600">{{ __('Confidential client (has a secret - use for server-side apps, not mobile/SPA)') }}</span>
                </label>
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-button>{{ __('Register') }}</x-button>
        </x-slot>
    </x-form-section>

    @if ($newClientSecret)
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-sm font-medium text-yellow-800">
                {{ __('Copy your client secret now - it will not be shown again.') }}
            </p>
            <dl class="mt-2 text-sm">
                <dt class="font-medium text-gray-700">{{ __('Client ID') }}</dt>
                <dd class="font-mono text-gray-900 mb-2">{{ $newClientId }}</dd>
                <dt class="font-medium text-gray-700">{{ __('Client Secret') }}</dt>
                <dd class="font-mono text-gray-900">{{ $newClientSecret }}</dd>
            </dl>
            <button type="button" wire:click="dismissNewClient" class="mt-3 text-sm text-yellow-800 underline">
                {{ __('Done') }}
            </button>
        </div>
    @endif

    <div class="mt-10 bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($clients as $client)
            <div class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ $client->name }}</div>
                    <div class="text-xs text-gray-500">{{ __('Client ID') }}: {{ $client->id }}</div>
                    <div class="text-xs text-gray-500">
                        {{ __('Redirect') }}: {{ implode(', ', $client->redirect_uris ?? []) }}
                    </div>
                </div>

                <button type="button" wire:click="revoke('{{ $client->id }}')"
                        wire:confirm="{{ __('Revoke this app? Connected integrations will stop working.') }}"
                        class="text-sm text-red-600 hover:text-red-900">
                    {{ __('Revoke') }}
                </button>
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No OAuth apps registered yet.') }}
            </div>
        @endforelse
    </div>
</div>
