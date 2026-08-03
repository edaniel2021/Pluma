<div>
    <div class="flex justify-end mb-4">
        <a href="{{ route('launches.compose') }}">
            <x-button>{{ __('New Launch') }}</x-button>
        </a>
    </div>

    <div class="bg-white shadow sm:rounded-lg p-4"
         wire:ignore
         x-data="launchesCalendar(@js($events), @js($timezone))"
         data-compose-url="{{ route('launches.compose') }}"
         data-edit-url-base="{{ url('/launches') }}">
        <div x-ref="calendar"></div>
    </div>
</div>
