<div>
    <div class="bg-white shadow sm:rounded-lg p-6 mb-6">
        <h3 class="text-sm font-medium text-gray-500 mb-3">{{ __('Connect a new account') }}</h3>

        <div class="flex flex-wrap gap-3">
            @foreach ($connectableProviders as $key => $provider)
                <a href="{{ route('integrations.redirect', $key) }}">
                    <x-secondary-button type="button">
                        {{ __('Connect :label', ['label' => $provider->label()]) }}
                    </x-secondary-button>
                </a>
            @endforeach
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($integrations as $integration)
            <div class="p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if ($integration->avatar_url)
                        <img src="{{ $integration->avatar_url }}" alt="" class="size-10 rounded-full object-cover">
                    @endif

                    <div>
                        <div class="text-sm font-medium text-gray-900">
                            {{ $integration->account_name ?? $integration->account_id }}
                        </div>
                        <div class="text-xs text-gray-500 flex items-center gap-2">
                            <span class="capitalize">{{ $integration->provider }}</span>

                            @if ($integration->isDisabled())
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                    {{ __('Needs reconnect') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <button type="button" wire:click="disconnect({{ $integration->id }})" wire:confirm="{{ __('Disconnect this account?') }}"
                        class="text-sm text-red-600 hover:text-red-900">
                    {{ __('Disconnect') }}
                </button>
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No accounts connected yet.') }}
            </div>
        @endforelse
    </div>
</div>
