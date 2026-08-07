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
                        @if ($post->integration)
                            <x-social-icon :provider="$post->integration->provider" />
                        @endif

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

                    @if ($post->engagement_fetched_at)
                        <div class="mt-1.5 flex items-center gap-4 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1">
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                                </svg>
                                {{ $post->likes_count ?? 0 }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                </svg>
                                {{ $post->comments_count ?? 0 }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                                </svg>
                                {{ $post->shares_count ?? 0 }}
                            </span>
                        </div>
                    @endif
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
