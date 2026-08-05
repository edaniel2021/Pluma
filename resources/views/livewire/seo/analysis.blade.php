<div @if ($this->isWaiting) wire:poll.3s @endif>
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900">{{ $website->url }}</h3>

        <div class="flex items-center gap-3">
            @if ($latestAnalysis)
                <button type="button" wire:click="exportCsv" class="text-sm text-indigo-600 hover:text-indigo-900">
                    {{ __('Export CSV') }}
                </button>
            @endif

            <x-button type="button" wire:click="runAnalysis" wire:loading.attr="disabled" :disabled="$this->isWaiting">
                {{ $this->isWaiting ? __('Analyzing...') : __('Run analysis now') }}
            </x-button>
        </div>
    </div>

    @if ($this->analysisError)
        <div class="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-4">
            {{ __('The last analysis attempt failed:') }} {{ $this->analysisError }}
        </div>
    @endif

    @if (! $latestAnalysis)
        <div class="mt-6 bg-white shadow sm:rounded-lg p-6 text-center text-sm text-gray-500">
            {{ __('No analysis yet - click "Run analysis now" to get started.') }}
        </div>
    @else
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Desktop Score') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $latestAnalysis->desktop_score ?? '—' }}</div>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Desktop Response') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $latestAnalysis->desktop_response_ms !== null ? $latestAnalysis->desktop_response_ms.' ms' : '—' }}</div>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Mobile Score') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $latestAnalysis->mobile_score ?? '—' }}</div>
            </div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Mobile Response') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $latestAnalysis->mobile_response_ms !== null ? $latestAnalysis->mobile_response_ms.' ms' : '—' }}</div>
            </div>
        </div>

        <div class="mt-6 bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ __('Page Content') }}</div>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-gray-500">{{ __('Title') }}</dt>
                    <dd class="text-gray-900">{{ $latestAnalysis->title ?? __('Missing') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Meta Description') }}</dt>
                    <dd class="text-gray-900">{{ $latestAnalysis->meta_description ?? __('Missing') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('H1s') }}</dt>
                    <dd class="text-gray-900">
                        @forelse ($latestAnalysis->h1s ?? [] as $h1)
                            <div>{{ $h1 }}</div>
                        @empty
                            {{ __('None found') }}
                        @endforelse
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('H2s') }}</dt>
                    <dd class="text-gray-900">
                        @forelse ($latestAnalysis->h2s ?? [] as $h2)
                            <div>{{ $h2 }}</div>
                        @empty
                            {{ __('None found') }}
                        @endforelse
                    </dd>
                </div>
            </dl>
        </div>

        <div class="mt-6 bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ __('Keyword Rankings') }}</div>

            @if ($keywordMetrics->isEmpty())
                <p class="text-sm text-gray-500">
                    {{ __('No Search Console data - map this website to a verified property to see keyword rankings.') }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                                <th class="pb-2">{{ __('Query') }}</th>
                                <th class="pb-2">{{ __('Clicks') }}</th>
                                <th class="pb-2">{{ __('Impressions') }}</th>
                                <th class="pb-2">{{ __('CTR') }}</th>
                                <th class="pb-2">{{ __('Position') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($keywordMetrics as $metric)
                                <tr>
                                    <td class="py-1.5 text-gray-900">{{ $metric->query }}</td>
                                    <td class="py-1.5 text-gray-700">{{ $metric->clicks }}</td>
                                    <td class="py-1.5 text-gray-700">{{ $metric->impressions }}</td>
                                    <td class="py-1.5 text-gray-700">{{ number_format($metric->ctr * 100, 1) }}%</td>
                                    <td class="py-1.5 text-gray-700">{{ number_format($metric->position, 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($trendingKeywords->isNotEmpty())
            <div class="mt-6 bg-white shadow sm:rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ __('Trending Keywords') }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                                <th class="pb-2">{{ __('Query') }}</th>
                                <th class="pb-2">{{ __('Clicks') }}</th>
                                <th class="pb-2">{{ __('Previous Period') }}</th>
                                <th class="pb-2">{{ __('Change') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($trendingKeywords as $trend)
                                <tr>
                                    <td class="py-1.5 text-gray-900">{{ $trend['query'] }}</td>
                                    <td class="py-1.5 text-gray-700">{{ $trend['clicks'] }}</td>
                                    <td class="py-1.5 text-gray-700">{{ $trend['previous_clicks'] }}</td>
                                    <td class="py-1.5 {{ $trend['delta'] > 0 ? 'text-green-600' : ($trend['delta'] < 0 ? 'text-red-600' : 'text-gray-500') }}">
                                        {{ $trend['delta'] > 0 ? '+' : '' }}{{ $trend['delta'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
