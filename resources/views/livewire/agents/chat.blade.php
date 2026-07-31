<div @if ($this->isWaiting) wire:poll.3s @endif>
    <div class="bg-white shadow sm:rounded-lg p-4 space-y-4 max-h-[60vh] overflow-y-auto">
        @forelse ($messages as $message)
            @if ($message->role === \App\Domain\Agents\Enums\AgentMessageRole::User)
                <div class="flex justify-end">
                    <div class="max-w-lg rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm whitespace-pre-wrap">
                        {{ $message->content }}
                    </div>
                </div>
            @elseif ($message->role === \App\Domain\Agents\Enums\AgentMessageRole::Assistant && $message->content)
                <div class="flex justify-start">
                    <div class="max-w-lg rounded-lg bg-gray-100 text-gray-900 px-4 py-2 text-sm whitespace-pre-wrap">
                        {{ $message->content }}
                    </div>
                </div>
            @elseif ($message->role === \App\Domain\Agents\Enums\AgentMessageRole::Assistant)
                <div class="flex justify-start">
                    <div class="max-w-lg rounded-lg bg-gray-50 border border-dashed border-gray-300 text-gray-500 px-4 py-2 text-xs italic">
                        {{ __('Calling tools...') }}
                    </div>
                </div>
            @endif
        @empty
            <p class="text-sm text-gray-500 text-center">{{ __('Say hello to get started.') }}</p>
        @endforelse

        @if ($this->isWaiting)
            <div class="flex justify-start">
                <div class="max-w-lg rounded-lg bg-gray-50 text-gray-400 px-4 py-2 text-sm italic">
                    {{ __('Thinking...') }}
                </div>
            </div>
        @endif
    </div>

    <form wire:submit="send" class="mt-4 flex gap-3">
        <x-input type="text" class="flex-1" wire:model="content"
                  placeholder="{{ __('Ask the assistant to draft or schedule a post...') }}" autofocus
                  :disabled="$this->isWaiting" />
        <x-button :disabled="$this->isWaiting">{{ __('Send') }}</x-button>
    </form>
    <x-input-error for="content" class="mt-2" />
</div>
