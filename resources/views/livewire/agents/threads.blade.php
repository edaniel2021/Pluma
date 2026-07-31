<div>
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-gray-600">
            {{ __('Chat with the AI assistant to draft, generate images for, and schedule posts.') }}
        </p>
        <x-button wire:click="create">{{ __('New Chat') }}</x-button>
    </div>

    <div class="bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($threads as $thread)
            <a href="{{ route('agents.show', $thread) }}" class="block p-4 hover:bg-gray-50">
                <div class="text-sm font-medium text-gray-900">
                    {{ $thread->title ?? __('New chat') }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ __('Started') }} {{ $thread->created_at->diffForHumans() }}
                </div>
            </a>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No conversations yet - start one to get help drafting and scheduling posts.') }}
            </div>
        @endforelse
    </div>
</div>
