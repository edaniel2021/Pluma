<?php

namespace App\Domain\Seo\Actions;

use App\Domain\Seo\Models\SeoKeywordMetric;
use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Support\Collection;

/**
 * A pure diff between a website's two most recently pulled keyword
 * periods, sorted by biggest positive click delta - nothing here is
 * stored; "trending" is always computed fresh on read from
 * SeoKeywordMetric rows.
 */
class ComputeTrendingKeywords
{
    /**
     * @return Collection<int, array{query: string, clicks: int, previous_clicks: int, delta: int}>
     */
    public function execute(SeoWebsite $website): Collection
    {
        $periods = SeoKeywordMetric::where('seo_website_id', $website->id)
            ->select('period_start', 'period_end')
            ->distinct()
            ->orderByDesc('period_end')
            ->limit(2)
            ->get();

        if ($periods->count() < 2) {
            return collect();
        }

        [$current, $previous] = [$periods[0], $periods[1]];

        $currentMetrics = $this->metricsFor($website, $current->period_start->toDateString(), $current->period_end->toDateString());
        $previousMetrics = $this->metricsFor($website, $previous->period_start->toDateString(), $previous->period_end->toDateString());

        return $currentMetrics
            ->map(function (SeoKeywordMetric $metric) use ($previousMetrics) {
                $previousClicks = $previousMetrics->get($metric->query)?->clicks ?? 0;

                return [
                    'query' => $metric->query,
                    'clicks' => $metric->clicks,
                    'previous_clicks' => $previousClicks,
                    'delta' => $metric->clicks - $previousClicks,
                ];
            })
            ->sortByDesc('delta')
            ->values();
    }

    /**
     * @return Collection<string, SeoKeywordMetric>
     */
    private function metricsFor(SeoWebsite $website, string $periodStart, string $periodEnd): Collection
    {
        return SeoKeywordMetric::where('seo_website_id', $website->id)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->get()
            ->keyBy('query');
    }
}
