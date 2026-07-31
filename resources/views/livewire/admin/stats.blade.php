<div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Organizations') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $organizationCount }}</div>
        </div>
        <div class="bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Users') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $userCount }}</div>
        </div>
        <div class="bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Connected Channels') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $integrationCount }}</div>
        </div>
        <div class="bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('AI Chat Threads') }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $agentThreadCount }}</div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ __('Posts by State') }}</div>
            <dl class="space-y-1">
                @foreach ($postsByState as $label => $total)
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-gray-600">{{ $label }}</dt>
                        <dd class="font-medium text-gray-900">{{ $total }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ __('Publishing Errors') }}</div>
            <dl class="space-y-1">
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-gray-600">{{ __('Last 24 hours') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $errorsLast24Hours }}</dd>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <dt class="text-gray-600">{{ __('Last 7 days') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $errorsLast7Days }}</dd>
                </div>
            </dl>
            <a href="{{ route('admin.errors') }}" class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-900">
                {{ __('View all errors') }} &rarr;
            </a>
        </div>
    </div>
</div>
