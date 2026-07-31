<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $thread->title ?? __('New chat') }}
            </h2>
            <a href="{{ route('agents.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                {{ __('All chats') }}
            </a>
        </div>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
            @livewire('agents.chat', ['thread' => $thread])
        </div>
    </div>
</x-app-layout>
