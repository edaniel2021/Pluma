<div>
    <div class="mb-4 flex items-center gap-3">
        <label for="type" class="text-sm text-gray-600">{{ __('Filter by type') }}</label>
        <select id="type" wire:model.live="type"
                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
            <option value="">{{ __('All types') }}</option>
            <option value="token_expired">{{ __('Token expired') }}</option>
            <option value="platform_error">{{ __('Platform error') }}</option>
        </select>
    </div>

    <div class="bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($errors as $error)
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-gray-900">
                        {{ $error->post?->organization?->name ?? __('Unknown organization') }}
                        <span class="text-gray-400">&middot;</span>
                        {{ $error->post?->integration?->provider ?? __('unknown channel') }}
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        {{ $error->type === 'token_expired' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800' }}">
                        {{ $error->type }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-600">{{ $error->message }}</p>
                <div class="mt-1 text-xs text-gray-400">
                    {{ __('Post') }} #{{ $error->post_id }}
                    &middot; {{ __('Retry') }} {{ $error->retry_count }}
                    &middot; {{ $error->created_at->diffForHumans() }}
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No publishing errors recorded.') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $errors->links() }}
    </div>
</div>
