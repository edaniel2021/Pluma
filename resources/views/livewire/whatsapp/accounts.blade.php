<div>
    <x-form-section submit="connect">
        <x-slot name="title">
            {{ __('Connect a WhatsApp Business Account') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Complete Meta\'s Embedded Signup yourself first, then paste the resulting details here.') }}
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 sm:col-span-3">
                <x-label for="waba_id" value="{{ __('WhatsApp Business Account ID') }}" />
                <x-input id="waba_id" type="text" class="mt-1 block w-full" wire:model="waba_id" />
                <x-input-error for="waba_id" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <x-label for="phone_number_id" value="{{ __('Phone Number ID') }}" />
                <x-input id="phone_number_id" type="text" class="mt-1 block w-full" wire:model="phone_number_id" />
                <x-input-error for="phone_number_id" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <x-label for="display_phone_number" value="{{ __('Display Phone Number') }}" />
                <x-input id="display_phone_number" type="text" class="mt-1 block w-full" wire:model="display_phone_number" placeholder="+1 555 000 0000" />
                <x-input-error for="display_phone_number" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <x-label for="access_token" value="{{ __('Permanent Access Token') }}" />
                <x-input id="access_token" type="password" class="mt-1 block w-full" wire:model="access_token" />
                <x-input-error for="access_token" class="mt-2" />
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-button>
                {{ __('Connect') }}
            </x-button>
        </x-slot>
    </x-form-section>

    <div class="mt-10 bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($accounts as $account)
            <div class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-900">
                        {{ $account->display_phone_number ?? $account->phone_number_id }}
                    </div>
                    <div class="text-xs text-gray-500">{{ __('WABA') }} {{ $account->waba_id }}</div>
                </div>

                <div class="flex items-center gap-4">
                    <a href="{{ route('whatsapp.contacts', $account) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                        {{ __('Contacts') }}
                    </a>
                    <a href="{{ route('whatsapp.broadcasts', $account) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                        {{ __('Broadcasts') }}
                    </a>
                    <button type="button" wire:click="disconnect({{ $account->id }})" wire:confirm="{{ __('Disconnect this account?') }}"
                            class="text-sm text-red-600 hover:text-red-900">
                        {{ __('Disconnect') }}
                    </button>
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No WhatsApp accounts connected yet.') }}
            </div>
        @endforelse
    </div>
</div>
