<div>
    <x-form-section submit="addContact">
        <x-slot name="title">
            {{ __('Add Opted-In Contact') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Only add contacts who have actually opted in to receive messages from this number - Meta enforces this.') }}
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 sm:col-span-3">
                <x-label for="phone_number" value="{{ __('Phone Number') }}" />
                <x-input id="phone_number" type="text" class="mt-1 block w-full" wire:model="phone_number" placeholder="+1 555 000 0000" />
                <x-input-error for="phone_number" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <x-label for="name" value="{{ __('Name') }}" />
                <x-input id="name" type="text" class="mt-1 block w-full" wire:model="name" />
                <x-input-error for="name" class="mt-2" />
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-button>
                {{ __('Add') }}
            </x-button>
        </x-slot>
    </x-form-section>

    <div class="mt-10 bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($contacts as $contact)
            <div class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ $contact->name ?? $contact->phone_number }}</div>
                    <div class="text-xs text-gray-500">{{ $contact->phone_number }}</div>
                </div>

                <button type="button" wire:click="removeContact({{ $contact->id }})" wire:confirm="{{ __('Remove this contact?') }}"
                        class="text-sm text-red-600 hover:text-red-900">
                    {{ __('Remove') }}
                </button>
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No contacts yet.') }}
            </div>
        @endforelse
    </div>
</div>
