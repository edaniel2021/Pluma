<div>
    <x-form-section submit="save">
        <x-slot name="title">
            {{ $post ? __('Edit Launch') : __('New Launch') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Compose a post and schedule it to a connected account.') }}
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 sm:col-span-4">
                <x-label for="integration_id" value="{{ __('Account') }}" />
                <select id="integration_id" wire:model="integration_id"
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full">
                    @forelse ($integrations as $integration)
                        <option value="{{ $integration->id }}">
                            {{ ucfirst($integration->provider) }} - {{ $integration->account_name ?? $integration->account_id }}
                        </option>
                    @empty
                        <option value="" disabled>{{ __('No accounts connected yet') }}</option>
                    @endforelse
                </select>
                <x-input-error for="integration_id" class="mt-2" />

                @if ($integrations->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">
                        {{ __('Connect an account first.') }}
                        <a href="{{ route('integrations.index') }}" class="underline">{{ __('Go to Integrations') }}</a>
                    </p>
                @endif
            </div>

            <div class="col-span-6">
                <x-label value="{{ __('Content') }}" />

                <div wire:ignore x-data="postComposer(@js($content))" class="mt-1">
                    <div x-ref="editor" class="border border-gray-300 rounded-md shadow-sm p-3 min-h-[150px] prose max-w-none focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500"></div>
                </div>

                <x-input-error for="content" class="mt-2" />

                @if ($this->characterLimit)
                    <p class="mt-1 text-xs text-gray-500 {{ strlen($content) > $this->characterLimit ? 'text-red-600' : '' }}">
                        {{ strlen($content) }} / {{ $this->characterLimit }}
                    </p>
                @endif
            </div>

            <div class="col-span-6">
                <x-label for="upload" value="{{ __('Attachment (image or video)') }}" />

                @php $existingMedia = $post?->getFirstMedia('default'); @endphp

                @if ($existingMedia && ! $upload)
                    <div class="mt-2 flex items-center gap-3">
                        @if (str($existingMedia->mime_type)->startsWith('image/'))
                            <img src="{{ $existingMedia->getUrl() }}" alt="" class="h-16 w-16 object-cover rounded">
                        @else
                            <span class="text-sm text-gray-600">{{ $existingMedia->file_name }}</span>
                        @endif

                        <button type="button" wire:click="removeMedia" class="text-sm text-red-600 hover:text-red-900">
                            {{ __('Remove') }}
                        </button>
                    </div>
                @endif

                <input id="upload" type="file" wire:model="upload" class="mt-2 block w-full text-sm text-gray-600" />

                <div wire:loading wire:target="upload" class="text-sm text-gray-500 mt-1">
                    {{ __('Uploading...') }}
                </div>

                @if ($upload)
                    <div class="mt-2">
                        @if (str($upload->getMimeType())->startsWith('image/'))
                            <img src="{{ $upload->temporaryUrl() }}" alt="" class="h-16 w-16 object-cover rounded">
                        @else
                            <span class="text-sm text-gray-600">{{ $upload->getClientOriginalName() }}</span>
                        @endif
                    </div>
                @endif

                <x-input-error for="upload" class="mt-2" />
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
            <a href="{{ route('launches.index') }}" class="text-sm text-gray-600 hover:text-gray-900 me-4">
                {{ __('Cancel') }}
            </a>

            @if ($post)
                <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this launch?') }}"
                        class="text-sm text-red-600 hover:text-red-900 me-4">
                    {{ __('Delete') }}
                </button>
            @endif

            <x-button>
                {{ __('Save') }}
            </x-button>
        </x-slot>
    </x-form-section>
</div>
