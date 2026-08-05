<div class="space-y-10">
    <div class="bg-white shadow sm:rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900">{{ __('Google Search Console') }}</h3>

        @if (! $searchConsoleAccount)
            <p class="mt-2 text-sm text-gray-500">
                {{ __('Connect a Google account to pull keyword rankings for your tracked websites.') }}
            </p>
            <a href="{{ route('search-console.redirect') }}" class="mt-4 inline-block">
                <x-button type="button">{{ __('Connect Google Search Console') }}</x-button>
            </a>
        @elseif ($searchConsoleAccount->isDisabled())
            <p class="mt-2 text-sm text-red-600">
                {{ __('Your Google Search Console connection needs to be reconnected.') }}
            </p>
            <a href="{{ route('search-console.redirect') }}" class="mt-4 inline-block">
                <x-button type="button">{{ __('Reconnect') }}</x-button>
            </a>
        @else
            <div class="mt-2 flex items-center justify-between">
                <p class="text-sm text-gray-700">
                    {{ __('Connected as :email', ['email' => $searchConsoleAccount->google_email]) }}
                </p>
                <button type="button" wire:click="disconnectSearchConsole({{ $searchConsoleAccount->id }})"
                        wire:confirm="{{ __('Disconnect Google Search Console? Websites mapped to it will keep their existing keyword data but stop refreshing.') }}"
                        class="text-sm text-red-600 hover:text-red-900">
                    {{ __('Disconnect') }}
                </button>
            </div>

            @if ($availableSitesError)
                <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3">
                    {{ __('Could not load your Search Console properties:') }} {{ $availableSitesError }}
                </div>
            @endif
        @endif
    </div>

    <x-form-section submit="addWebsite">
        <x-slot name="title">
            {{ __('Add a Website') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Each website is analyzed at its homepage URL for now - crawling additional pages is a later phase.') }}
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 sm:col-span-4">
                <x-label for="url" value="{{ __('Website URL') }}" />
                <x-input id="url" type="url" class="mt-1 block w-full" wire:model="url" placeholder="https://example.com" />
                <x-input-error for="url" class="mt-2" />
            </div>

            @if ($searchConsoleAccount && ! $searchConsoleAccount->isDisabled())
                <div class="col-span-6 sm:col-span-4">
                    <x-label for="search_console_site_url" value="{{ __('Search Console Property (optional)') }}" />
                    <select id="search_console_site_url" wire:model="search_console_site_url"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">
                        <option value="">{{ __('Not mapped - crawl and PageSpeed only') }}</option>
                        @foreach ($availableSites as $site)
                            <option value="{{ $site['siteUrl'] }}">{{ $site['siteUrl'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error for="search_console_site_url" class="mt-2" />
                </div>
            @endif
        </x-slot>

        <x-slot name="actions">
            <x-button>{{ __('Add Website') }}</x-button>
        </x-slot>
    </x-form-section>

    <div class="bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($websites as $website)
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $website->url }}</div>
                        @if ($website->search_console_site_url)
                            <div class="text-xs text-gray-500">{{ __('Mapped to') }} {{ $website->search_console_site_url }}</div>
                        @else
                            <div class="text-xs text-gray-400">{{ __('No Search Console property mapped') }}</div>
                        @endif
                    </div>

                    <div class="flex items-center gap-4">
                        <a href="{{ route('seo.websites.show', $website) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                            {{ __('View Analysis') }}
                        </a>
                        @if ($searchConsoleAccount && ! $searchConsoleAccount->isDisabled())
                            <button type="button" wire:click="editMapping({{ $website->id }})" class="text-sm text-indigo-600 hover:text-indigo-900">
                                {{ $website->search_console_site_url ? __('Edit mapping') : __('Map to Search Console') }}
                            </button>
                        @endif
                        <button type="button" wire:click="removeWebsite({{ $website->id }})" wire:confirm="{{ __('Remove this website?') }}"
                                class="text-sm text-red-600 hover:text-red-900">
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>

                @if ($editingWebsiteId === $website->id)
                    <div class="mt-3 flex items-end gap-3">
                        <div class="flex-1">
                            <x-label for="editing_search_console_site_url" value="{{ __('Search Console Property') }}" />
                            <select id="editing_search_console_site_url" wire:model="editing_search_console_site_url"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">
                                <option value="">{{ __('Not mapped - crawl and PageSpeed only') }}</option>
                                @foreach ($availableSites as $site)
                                    <option value="{{ $site['siteUrl'] }}">{{ $site['siteUrl'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-button type="button" wire:click="saveMapping({{ $website->id }})">{{ __('Save') }}</x-button>
                        <button type="button" wire:click="cancelEditingMapping" class="text-sm text-gray-600 hover:text-gray-900">
                            {{ __('Cancel') }}
                        </button>
                    </div>
                @endif
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No websites tracked yet.') }}
            </div>
        @endforelse
    </div>
</div>
