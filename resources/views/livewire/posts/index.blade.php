<div>
    <div class="flex justify-end mb-4">
        <a href="{{ route('posts.create') }}">
            <x-button>{{ __('New Post') }}</x-button>
        </a>
    </div>

    <div class="bg-white shadow sm:rounded-lg divide-y divide-gray-200">
        @forelse ($posts as $post)
            <div class="p-4 flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            @class([
                                'bg-gray-100 text-gray-800' => $post->state === \App\Domain\Posts\Enums\PostState::Draft,
                                'bg-yellow-100 text-yellow-800' => $post->state === \App\Domain\Posts\Enums\PostState::Queue,
                                'bg-green-100 text-green-800' => $post->state === \App\Domain\Posts\Enums\PostState::Published,
                                'bg-red-100 text-red-800' => $post->state === \App\Domain\Posts\Enums\PostState::Error,
                            ])">
                            {{ $post->state->label() }}
                        </span>

                        @if ($post->scheduled_at)
                            <span class="text-xs text-gray-500">{{ __('Scheduled for') }} {{ $post->scheduled_at->format('M j, Y g:i A') }}</span>
                        @endif
                    </div>

                    <p class="mt-1 text-sm text-gray-700 truncate">{{ $post->content }}</p>
                </div>

                <div class="ms-4 flex items-center gap-4">
                    <a href="{{ route('posts.edit', $post) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                        {{ __('Edit') }}
                    </a>

                    <button type="button" wire:click="delete({{ $post->id }})" wire:confirm="{{ __('Delete this post?') }}"
                            class="text-sm text-red-600 hover:text-red-900">
                        {{ __('Delete') }}
                    </button>
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">
                {{ __('No posts yet.') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $posts->links() }}
    </div>
</div>
