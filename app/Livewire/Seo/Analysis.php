<?php

namespace App\Livewire\Seo;

use App\Domain\Seo\Actions\ComputeTrendingKeywords;
use App\Domain\Seo\Actions\RunKeywordPageAnalysis;
use App\Domain\Seo\Jobs\RunKeywordPageAnalysisJob;
use App\Domain\Seo\Jobs\RunSiteAnalysisJob;
use App\Domain\Seo\Models\SeoKeywordMetric;
use App\Domain\Seo\Models\SeoPageAnalysis;
use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The on-demand site analysis report for a single website: latest crawl +
 * PageSpeed snapshot, the most recent Search Console keyword pull (if
 * mapped), trending keywords, and a CSV export.
 */
class Analysis extends Component
{
    public SeoWebsite $website;

    /**
     * ISO8601 timestamp of the last "Run analysis now" click - null means
     * no analysis is currently in flight. Used to detect completion (a
     * fresh SeoAnalysis row newer than this) rather than trusting a fixed
     * delay, same reasoning as the AI Assistant chat's wire:poll fallback.
     */
    public ?string $analysisRequestedAt = null;

    /**
     * Last-resort fallback if RunSiteAnalysisJob's failed() hook itself
     * never fires (e.g. the worker is killed outright rather than the job
     * throwing normally) - comfortably above the job's worst-case retry
     * runtime: 3 tries, up to 60s backoff between each, plus real
     * execution time (crawl + two PageSpeed calls + an optional GSC
     * query) per attempt.
     */
    private const MAX_WAIT_SECONDS = 480;

    /**
     * Comma-separated keyword list for the keyword-driven page analysis
     * flow - prefilled from the website's last submission on mount.
     */
    public string $keywordsInput = '';

    /**
     * Same "ephemeral component property, not persisted" pattern as
     * analysisRequestedAt, for the separate keyword-check async flow.
     */
    public ?string $keywordAnalysisRequestedAt = null;

    /**
     * Sized just above RunKeywordPageAnalysisJob's own $timeout=1320 -
     * that job has no retries (tries=1), so there's no backoff window to
     * add on top of the timeout, unlike MAX_WAIT_SECONDS above.
     */
    private const KEYWORD_MAX_WAIT_SECONDS = 1400;

    public function mount(SeoWebsite $website): void
    {
        $this->website = $website;
        $this->keywordsInput = $website->last_keyword_check_keywords
            ? implode(', ', $website->last_keyword_check_keywords)
            : '';
    }

    public function runAnalysis(): void
    {
        $this->analysisRequestedAt = now()->toISOString();

        RunSiteAnalysisJob::dispatch($this->website->id);
    }

    public function runKeywordAnalysis(): void
    {
        $this->validate(['keywordsInput' => ['required', 'string']]);

        if (! $this->website->search_console_site_url) {
            $this->addError('keywordsInput', __('Map this website to a Search Console property before checking keyword rankings.'));

            return;
        }

        $keywords = collect(explode(',', $this->keywordsInput))
            ->map(fn (string $keyword) => trim($keyword))
            ->filter()
            ->unique()
            ->values();

        if ($keywords->isEmpty()) {
            $this->addError('keywordsInput', __('Enter at least one keyword.'));

            return;
        }

        if ($keywords->count() > RunKeywordPageAnalysis::MAX_KEYWORDS_PER_SUBMISSION) {
            $this->addError('keywordsInput', __('Enter at most :max keywords.', ['max' => RunKeywordPageAnalysis::MAX_KEYWORDS_PER_SUBMISSION]));

            return;
        }

        $this->keywordAnalysisRequestedAt = now()->toISOString();

        RunKeywordPageAnalysisJob::dispatch($this->website->id, $keywords->all());
    }

    /**
     * Real production bug: RunSiteAnalysisJob used to have no failed()
     * handler, so a permanent job failure left this stuck true forever -
     * the only thing that could ever flip it false was a *successful*
     * fresh SeoAnalysis row appearing, which of course never happened on
     * failure. Now also stops waiting once a failure newer than the
     * request is recorded, or after MAX_WAIT_SECONDS regardless.
     */
    public function getIsWaitingProperty(): bool
    {
        if (! $this->analysisRequestedAt) {
            return false;
        }

        if (now()->timestamp - Carbon::parse($this->analysisRequestedAt)->timestamp > self::MAX_WAIT_SECONDS) {
            return false;
        }

        $website = $this->website->fresh();

        if ($website->last_analysis_failed_at && $website->last_analysis_failed_at->toISOString() >= $this->analysisRequestedAt) {
            return false;
        }

        return ! $website->analyses()
            ->where('analyzed_at', '>=', $this->analysisRequestedAt)
            ->exists();
    }

    /**
     * Only meaningful while a request is (or was) in flight - null means
     * either nothing has been requested yet, or the most recent attempt
     * succeeded/is still running.
     */
    public function getAnalysisErrorProperty(): ?string
    {
        if (! $this->analysisRequestedAt) {
            return null;
        }

        $website = $this->website->fresh();

        if ($website->last_analysis_failed_at && $website->last_analysis_failed_at->toISOString() >= $this->analysisRequestedAt) {
            return $website->last_analysis_error;
        }

        return null;
    }

    /**
     * Mirrors getIsWaitingProperty()'s pattern, but completion can't be
     * inferred from "does a fresh child row exist" like that one does -
     * a legitimately correct keyword-check result can be zero rank rows
     * (none of the submitted keywords rank for any page), which is
     * indistinguishable from "still running" under that approach. Uses
     * the explicit last_keyword_analysis_completed_at marker instead.
     */
    public function getIsKeywordAnalysisWaitingProperty(): bool
    {
        if (! $this->keywordAnalysisRequestedAt) {
            return false;
        }

        if (now()->timestamp - Carbon::parse($this->keywordAnalysisRequestedAt)->timestamp > self::KEYWORD_MAX_WAIT_SECONDS) {
            return false;
        }

        $website = $this->website->fresh();

        if ($website->last_keyword_analysis_failed_at && $website->last_keyword_analysis_failed_at->toISOString() >= $this->keywordAnalysisRequestedAt) {
            return false;
        }

        return ! ($website->last_keyword_analysis_completed_at && $website->last_keyword_analysis_completed_at->toISOString() >= $this->keywordAnalysisRequestedAt);
    }

    public function getKeywordAnalysisErrorProperty(): ?string
    {
        if (! $this->keywordAnalysisRequestedAt) {
            return null;
        }

        $website = $this->website->fresh();

        if ($website->last_keyword_analysis_failed_at && $website->last_keyword_analysis_failed_at->toISOString() >= $this->keywordAnalysisRequestedAt) {
            return $website->last_keyword_analysis_error;
        }

        return null;
    }

    public function exportCsv(): StreamedResponse
    {
        $latest = $this->latestAnalysis();
        $keywords = $this->latestKeywordMetrics();
        $pageAnalyses = $this->pageAnalyses();

        return response()->streamDownload(function () use ($latest, $keywords, $pageAnalyses) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Section', 'Field', 'Value']);
            fputcsv($handle, ['Page', 'Title', $latest?->title]);
            fputcsv($handle, ['Page', 'Meta Description', $latest?->meta_description]);
            fputcsv($handle, ['Page', 'H1s', implode(' | ', $latest?->h1s ?? [])]);
            fputcsv($handle, ['Page', 'H2s', implode(' | ', $latest?->h2s ?? [])]);
            fputcsv($handle, ['Performance', 'Desktop Response (ms)', $latest?->desktop_response_ms]);
            fputcsv($handle, ['Performance', 'Desktop Score', $latest?->desktop_score]);
            fputcsv($handle, ['Performance', 'Mobile Response (ms)', $latest?->mobile_response_ms]);
            fputcsv($handle, ['Performance', 'Mobile Score', $latest?->mobile_score]);
            fputcsv($handle, ['Robots.txt', 'Exists', $latest?->robots_txt_result['exists'] ?? null]);
            fputcsv($handle, ['Robots.txt', 'Blocks Indexing', $latest?->robots_txt_result['blocks_indexing'] ?? null]);
            fputcsv($handle, ['Sitemap', 'Exists', $latest?->sitemap_result['exists'] ?? null]);
            fputcsv($handle, ['Sitemap', 'Is Index', $latest?->sitemap_result['is_index'] ?? null]);
            fputcsv($handle, ['Sitemap', 'URL Count', $latest?->sitemap_result['url_count'] ?? null]);
            fputcsv($handle, []);
            fputcsv($handle, ['Query', 'Clicks', 'Impressions', 'CTR', 'Position']);

            foreach ($keywords as $metric) {
                fputcsv($handle, [$metric->query, $metric->clicks, $metric->impressions, $metric->ctr, $metric->position]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Keyword Page Rankings']);
            fputcsv($handle, ['Keyword', 'Page URL', 'Clicks', 'Impressions', 'CTR', 'Position']);

            foreach ($pageAnalyses as $page) {
                foreach ($page->keywordRanks as $rank) {
                    fputcsv($handle, [$rank->keyword, $page->page_url, $rank->clicks, $rank->impressions, $rank->ctr, $rank->position]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Page Analysis']);
            fputcsv($handle, ['Page URL', 'Title', 'Meta Description', 'H1s', 'H2s', 'Crawl Error']);

            foreach ($pageAnalyses as $page) {
                fputcsv($handle, [$page->page_url, $page->title, $page->meta_description, implode(' | ', $page->h1s ?? []), implode(' | ', $page->h2s ?? []), $page->crawl_error]);
            }

            fclose($handle);
        }, str($this->website->url)->slug().'-seo-analysis.csv');
    }

    private function latestAnalysis()
    {
        return $this->website->analyses()->latest('analyzed_at')->first();
    }

    /**
     * @return Collection<int, SeoKeywordMetric>
     */
    private function latestKeywordMetrics(): Collection
    {
        $latestPeriodEnd = $this->website->keywordMetrics()->max('period_end');

        if (! $latestPeriodEnd) {
            return collect();
        }

        return $this->website->keywordMetrics()
            ->where('period_end', $latestPeriodEnd)
            ->orderByDesc('clicks')
            ->get();
    }

    /**
     * Only meaningful once at least one keyword check has been submitted -
     * an empty collection before that renders as "no section yet" rather
     * than "checked, nothing found." Sorted by each page's aggregate
     * clicks across its ranking keywords, highest first.
     *
     * @return Collection<int, SeoPageAnalysis>
     */
    private function pageAnalyses(): Collection
    {
        if (! $this->website->last_keyword_check_keywords) {
            return collect();
        }

        return $this->website->pageAnalyses()
            ->with('keywordRanks')
            ->get()
            ->sortByDesc(fn (SeoPageAnalysis $page) => $page->keywordRanks->sum('clicks'))
            ->values();
    }

    /**
     * The clicks/impressions/position figures are a rolling window, not a
     * lifetime total - every rank row from one run shares the same
     * period_start/period_end, so the first one found is representative
     * of the whole result set.
     *
     * @param  Collection<int, SeoPageAnalysis>  $pageAnalyses
     * @return array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon}|null
     */
    private function keywordCheckPeriod(Collection $pageAnalyses): ?array
    {
        $firstRank = $pageAnalyses->flatMap(fn (SeoPageAnalysis $page) => $page->keywordRanks)->first();

        if (! $firstRank) {
            return null;
        }

        return ['start' => $firstRank->period_start, 'end' => $firstRank->period_end];
    }

    public function render(ComputeTrendingKeywords $trending)
    {
        $pageAnalyses = $this->pageAnalyses();

        return view('livewire.seo.analysis', [
            'latestAnalysis' => $this->latestAnalysis(),
            'keywordMetrics' => $this->latestKeywordMetrics(),
            'trendingKeywords' => $trending->execute($this->website),
            'pageAnalyses' => $pageAnalyses,
            'keywordCheckPeriod' => $this->keywordCheckPeriod($pageAnalyses),
        ]);
    }
}
