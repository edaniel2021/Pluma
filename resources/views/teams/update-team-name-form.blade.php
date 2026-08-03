<x-form-section submit="updateTeamName">
    <x-slot name="title">
        {{ __('Organization Name') }}
    </x-slot>

    <x-slot name="description">
        {{ __('The organization\'s name and owner information.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Organization Owner Information -->
        <div class="col-span-6">
            <x-label value="{{ __('Organization Owner') }}" />

            <div class="flex items-center mt-2">
                <img class="size-12 rounded-full object-cover" src="{{ $team->owner->profile_photo_url }}" alt="{{ $team->owner->name }}">

                <div class="ms-4 leading-tight">
                    <div class="text-gray-900">{{ $team->owner->name }}</div>
                    <div class="text-gray-700 text-sm">{{ $team->owner->email }}</div>
                </div>
            </div>
        </div>

        <!-- Organization Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="name" value="{{ __('Organization Name') }}" />

            <x-input id="name"
                        type="text"
                        class="mt-1 block w-full"
                        wire:model="state.name"
                        :disabled="! Gate::check('update', $team)" />

            <x-input-error for="name" class="mt-2" />
        </div>

        <!-- Organization Timezone -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="timezone" value="{{ __('Timezone') }}" />

            <select id="timezone"
                    wire:model="state.timezone"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                    @disabled(! Gate::check('update', $team))>
                @foreach (\DateTimeZone::listIdentifiers() as $timezone)
                    <option value="{{ $timezone }}">{{ $timezone }}</option>
                @endforeach
            </select>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('All post scheduling for this organization is interpreted in this timezone, regardless of each member\'s own local time.') }}
            </p>

            <x-input-error for="timezone" class="mt-2" />
        </div>
    </x-slot>

    @if (Gate::check('update', $team))
        <x-slot name="actions">
            <x-action-message class="me-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button>
                {{ __('Save') }}
            </x-button>
        </x-slot>
    @endif
</x-form-section>
