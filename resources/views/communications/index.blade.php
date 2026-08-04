<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Communications') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6 text-center">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Coming soon') }}</h3>
                <p class="mt-2 text-sm text-gray-500">
                    {{ __('Communications features (email, SMS, and direct messaging campaigns) are planned for a future phase.') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
