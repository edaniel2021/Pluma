<div @if ($this->isWaiting || $this->isKeywordAnalysisWaiting) wire:poll.3s @endif>
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
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ __('Robots.txt & Sitemap') }}</div>

            @php $robots = $latestAnalysis->robots_txt_result; $sitemap = $latestAnalysis->sitemap_result; @endphp

            @if ($robots && ($robots['blocks_indexing'] ?? false))
                <div class="mb-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3">
                    {{ __('robots.txt blocks all crawlers from the entire site (Disallow: /).') }}
                </div>
            @endif

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">{{ __('robots.txt') }}</dt>
                    <dd class="text-gray-900">
                        @if (! $robots || ! $robots['exists'])
                            {{ __('Not found') }}
                        @else
                            {{ __(':count disallow rule(s) found', ['count' => count($robots['disallow_rules'] ?? [])]) }}
                            @if ($robots['parse_error'] ?? null)
                                <div class="text-red-600">{{ $robots['parse_error'] }}</div>
                            @endif
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Sitemap') }}</dt>
                    <dd class="text-gray-900">
                        @if (! $sitemap || ! $sitemap['exists'])
                            {{ __('Not found at') }} {{ $sitemap['url'] ?? '—' }}
                        @elseif ($sitemap['is_index'])
                            {{ __('Sitemap index with :count child sitemap(s)', ['count' => $sitemap['child_sitemap_count'] ?? 0]) }}
                        @else
                            {{ __(':count URL(s) listed', ['count' => $sitemap['url_count'] ?? 0]) }}
                        @endif
                        @if ($sitemap['parse_error'] ?? null)
                            <div class="text-red-600">{{ $sitemap['parse_error'] }}</div>
                        @endif
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

        <div class="mt-6 bg-white shadow sm:rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">{{ __('Check Keyword Rankings') }}</div>
            <p class="text-sm text-gray-500 mb-3">
                {{ __('Enter comma-separated keywords to see which of your pages rank for them (Search Console data) plus an on-page summary of those pages - up to :max keywords per check.', ['max' => \App\Domain\Seo\Actions\RunKeywordPageAnalysis::MAX_KEYWORDS_PER_SUBMISSION]) }}
            </p>

            @if (! $website->search_console_site_url)
                <p class="text-sm text-gray-400">{{ __('Map this website to a Search Console property to use this.') }}</p>
            @else
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-label for="keywordsInput" value="{{ __('Keywords') }}" />
                        <x-input id="keywordsInput" type="text" class="mt-1 block w-full" wire:model="keywordsInput" placeholder="keyword one, keyword two" />
                        <x-input-error for="keywordsInput" class="mt-2" />
                    </div>
                    <x-button type="button" wire:click="runKeywordAnalysis" wire:loading.attr="disabled" :disabled="$this->isKeywordAnalysisWaiting">
                        {{ $this->isKeywordAnalysisWaiting ? __('Checking...') : __('Check keyword rankings') }}
                    </x-button>
                </div>

                @if ($this->keywordAnalysisError)
                    <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3">
                        {{ __('The last keyword check failed:') }} {{ $this->keywordAnalysisError }}
                    </div>
                @endif
            @endif

            @if ($pageAnalyses->isNotEmpty())
                @if ($keywordCheckPeriod)
                    <div class="mt-4 flex items-center gap-1.5 text-xs text-gray-500">
                        <svg class="size-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        {{ __('Data from :start to :end', ['start' => $keywordCheckPeriod['start']->format('M j'), 'end' => $keywordCheckPeriod['end']->format('M j, Y')]) }}
                    </div>
                @endif

                <div class="mt-4 space-y-5">
                    @foreach ($pageAnalyses as $page)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center gap-2">
                                <svg class="size-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                </svg>
                                <a href="{{ $page->page_url }}" target="_blank" rel="noopener" class="text-sm font-medium text-gray-900 break-all hover:text-indigo-600">{{ $page->page_url }}</a>
                            </div>

                            @if ($page->crawled_at)
                                <dl class="mt-3 space-y-1.5 text-sm">
                                    <div><dt class="inline text-gray-500">{{ __('Title') }}: </dt><dd class="inline text-gray-900">{{ $page->title ?? __('Missing') }}</dd></div>
                                    <div><dt class="inline text-gray-500">{{ __('Meta Description') }}: </dt><dd class="inline text-gray-900">{{ $page->meta_description ?? __('Missing') }}</dd></div>
                                </dl>
                            @elseif ($page->crawl_error)
                                <div class="mt-3 text-sm text-red-600">{{ __('Could not crawl this page:') }} {{ $page->crawl_error }}</div>
                            @else
                                <div class="mt-3 text-sm text-gray-400">{{ __('Not crawled this run - over the per-run page limit.') }}</div>
                            @endif

                            <div class="mt-3 pt-3 border-t border-gray-100 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                                            <th class="pb-2 pr-4">{{ __('Keyword') }}</th>
                                            <th class="pb-2 pr-4">
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="size-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672Zm-7.518-.267A8.25 8.25 0 1 1 20.25 10.5M8.288 14.212A5.25 5.25 0 1 1 17.25 10.5" />
                                                    </svg>
                                                    {{ __('Clicks') }}
                                                </span>
                                            </th>
                                            <th class="pb-2 pr-4">
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="size-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    {{ __('Impressions') }}
                                                </span>
                                            </th>
                                            <th class="pb-2">
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="size-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                                    </svg>
                                                    {{ __('Position') }}
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($page->keywordRanks as $rank)
                                            <tr>
                                                <td class="py-2 pr-4 text-gray-900">{{ $rank->keyword }}</td>
                                                <td class="py-2 pr-4 text-gray-700">{{ $rank->clicks }}</td>
                                                <td class="py-2 pr-4 text-gray-700">{{ $rank->impressions }}</td>
                                                <td class="py-2 text-gray-700">{{ number_format($rank->position, 1) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($website->last_keyword_check_keywords && ! $this->isKeywordAnalysisWaiting)
                <p class="mt-4 text-sm text-gray-500">{{ __('None of the submitted keywords rank for any page yet.') }}</p>
            @endif
        </div>
    @endif
</div>
