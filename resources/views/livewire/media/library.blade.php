<div>
    <div class="bg-white shadow sm:rounded-lg p-6 mb-6">
        <form wire:submit="save" class="flex items-center gap-4">
            <input type="file" wire:model="upload" class="block w-full text-sm text-gray-600" />

            <x-button type="submit">
                {{ __('Upload') }}
            </x-button>
        </form>

        <x-input-error for="upload" class="mt-2" />

        <div wire:loading wire:target="upload" class="text-sm text-gray-500 mt-2">
            {{ __('Uploading...') }}
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse ($media as $item)
            <div class="relative group bg-white shadow sm:rounded-lg overflow-hidden">
                @if (str($item->mime_type)->startsWith('image/'))
                    <img src="{{ $item->getUrl() }}" alt="{{ $item->name }}" class="w-full h-24 object-cover">
                @else
                    <div class="w-full h-24 flex items-center justify-center bg-gray-50 text-xs text-gray-500 p-2 text-center break-all">
                        {{ $item->file_name }}
                    </div>
                @endif

                <button type="button" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Delete this file?') }}"
                        class="absolute top-1 right-1 bg-white/90 rounded-full p-1 text-red-600 opacity-0 group-hover:opacity-100 transition text-xs">
                    &times;
                </button>
            </div>
        @empty
            <div class="col-span-full text-center text-sm text-gray-500 py-6">
                {{ __('No media yet.') }}
            </div>
        @endforelse
    </div>
</div>
