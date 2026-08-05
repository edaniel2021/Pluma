<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Site Analysis') }}
            </h2>
            <a href="{{ route('seo.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                {{ __('Back to SEO') }}
            </a>
        </div>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
            @livewire('seo.analysis', ['website' => $website])
        </div>
    </div>
</x-app-layout>
