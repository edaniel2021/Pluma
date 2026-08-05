<?php

namespace App\Livewire\Seo;

use App\Domain\Seo\Actions\ComputeTrendingKeywords;
use App\Domain\Seo\Jobs\RunSiteAnalysisJob;
use App\Domain\Seo\Models\SeoKeywordMetric;
use App\Domain\Seo\Models\SeoWebsite;
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

    public function mount(SeoWebsite $website): void
    {
        $this->website = $website;
    }

    public function runAnalysis(): void
    {
        $this->analysisRequestedAt = now()->toISOString();

        RunSiteAnalysisJob::dispatch($this->website->id);
    }

    public function getIsWaitingProperty(): bool
    {
        if (! $this->analysisRequestedAt) {
            return false;
        }

        return ! $this->website->analyses()
            ->where('analyzed_at', '>=', $this->analysisRequestedAt)
            ->exists();
    }

    public function exportCsv(): StreamedResponse
    {
        $latest = $this->latestAnalysis();
        $keywords = $this->latestKeywordMetrics();

        return response()->streamDownload(function () use ($latest, $keywords) {
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
            fputcsv($handle, []);
            fputcsv($handle, ['Query', 'Clicks', 'Impressions', 'CTR', 'Position']);

            foreach ($keywords as $metric) {
                fputcsv($handle, [$metric->query, $metric->clicks, $metric->impressions, $metric->ctr, $metric->position]);
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

    public function render(ComputeTrendingKeywords $trending)
    {
        return view('livewire.seo.analysis', [
            'latestAnalysis' => $this->latestAnalysis(),
            'keywordMetrics' => $this->latestKeywordMetrics(),
            'trendingKeywords' => $trending->execute($this->website),
        ]);
    }
}
