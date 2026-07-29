<div>
    <x-form-section submit="send">
        <x-slot name="title">
            {{ __('Send a Broadcast') }}
        </x-slot>

        <x-slot name="description">
            {{ __('The template name/language must already be approved in your Meta Business account.') }}
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 sm:col-span-3">
                <x-label for="template_name" value="{{ __('Template Name') }}" />
                <x-input id="template_name" type="text" class="mt-1 block w-full" wire:model="template_name" placeholder="hello_world" />
                <x-input-error for="template_name" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <x-label for="template_language" value="{{ __('Template Language') }}" />
                <x-input id="template_language" type="text" class="mt-1 block w-full" wire:model="template_language" />
                <x-input-error for="template_language" class="mt-2" />
            </div>

            <div class="col-span-6">
                <x-label value="{{ __('Recipients') }}" />
                <x-input-error for="contact_ids" class="mt-2" />

                <div class="mt-2 space-y-2 max-h-48 overflow-y-auto border border-gray-200 rounded-md p-3">
                    @forelse ($contacts as $contact)
                        <label class="flex items-center gap-2">
                            <x-checkbox wire:model="contact_ids" value="{{ $contact->id }}" />
                            <span class="text-sm text-gray-700">{{ $contact->name ?? $contact->phone_number }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">
                            {{ __('No contacts yet.') }}
                            <a href="{{ route('whatsapp.contacts', $account) }}" class="underline">{{ __('Add some first') }}</a>
                        </p>
                    @endforelse
                </div>
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-button>
                {{ __('Send') }}
            </x-button>
        </x-slot>
    </x-form-section>

    <div class="mt-10 bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($broadcasts as $broadcast)
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-gray-900">
                        {{ $broadcast->template_name }} ({{ $broadcast->template_language }})
                    </div>

                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        @class([
                            'bg-gray-100 text-gray-800' => $broadcast->status === 'draft',
                            'bg-yellow-100 text-yellow-800' => $broadcast->status === 'sending',
                            'bg-green-100 text-green-800' => $broadcast->status === 'sent',
                            'bg-red-100 text-red-800' => $broadcast->status === 'failed',
                        ])">
                        {{ ucfirst($broadcast->status) }}
                    </span>
                </div>

                <div class="mt-1 text-xs text-gray-500">
                    {{ __(':sent sent, :failed failed, :pending pending', [
                        'sent' => $broadcast->recipients->where('status', 'sent')->count(),
                        'failed' => $broadcast->recipients->where('status', 'failed')->count(),
                        'pending' => $broadcast->recipients->where('status', 'pending')->count(),
                    ]) }}
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No broadcasts sent yet.') }}
            </div>
        @endforelse
    </div>
</div>
