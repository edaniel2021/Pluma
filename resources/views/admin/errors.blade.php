<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Admin: Publishing Errors') }}
            </h2>
            <a href="{{ route('admin.stats') }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                {{ __('Stats') }}
            </a>
        </div>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @livewire('admin.errors')
        </div>
    </div>
</x-app-layout>
