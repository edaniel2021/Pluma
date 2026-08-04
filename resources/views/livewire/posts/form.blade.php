<div>
    <x-form-section submit="save">
        <x-slot name="title">
            {{ $post ? __('Edit Post') : __('New Post') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Plain post content for now - scheduling and publishing to social platforms come in a later phase.') }}
        </x-slot>

        <x-slot name="form">
            @if ($this->existingMedia)
                <div class="col-span-6">
                    <x-label value="{{ __('Attached Image') }}" />
                    <img src="{{ $this->existingMedia->getUrl() }}" alt="{{ __('Attached image') }}"
                         class="mt-1 rounded-lg border border-gray-200 max-w-xs">
                </div>
            @endif

            <div class="col-span-6">
                <x-label for="content" value="{{ __('Content') }}" />
                <textarea id="content" wire:model="content" rows="6"
                          class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"></textarea>
                <x-input-error for="content" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <x-label for="state" value="{{ __('State') }}" />
                <select id="state" wire:model="state"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">
                    @foreach ($this->states as $stateOption)
                        <option value="{{ $stateOption->value }}">{{ $stateOption->label() }}</option>
                    @endforeach
                </select>
                <x-input-error for="state" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <x-label for="scheduled_at" value="{{ __('Scheduled For') }}" />
                <x-input id="scheduled_at" type="datetime-local" class="mt-1 block w-full" wire:model="scheduled_at" />
                <x-input-error for="scheduled_at" class="mt-2" />
            </div>
        </x-slot>

        <x-slot name="actions">
            <a href="{{ route('posts.index') }}" class="text-sm text-gray-600 hover:text-gray-900 me-4">
                {{ __('Cancel') }}
            </a>

            <x-button>
                {{ __('Save') }}
            </x-button>
        </x-slot>
    </x-form-section>
</div>
