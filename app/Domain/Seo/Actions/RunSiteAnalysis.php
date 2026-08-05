<?php

namespace App\Domain\Seo\Actions;

use App\Domain\Seo\Models\SeoAnalysis;
use App\Domain\Seo\Models\SeoKeywordMetric;
use App\Domain\Seo\Models\SeoWebsite;
use App\Domain\Seo\Support\PageCrawler;
use App\Domain\Seo\Support\PageSpeedClient;
use App\Domain\Seo\Support\SearchConsoleClient;

/**
 * Orchestrates a crawl + two PageSpeed calls + (if mapped) a Search
 * Console keyword pull for one website, persisting a SeoAnalysis snapshot
 * and refreshed SeoKeywordMetric rows. Reused unchanged by both the
 * on-demand "Run analysis now" button and the (future) monthly audit job -
 * no duplicated crawl/pagespeed/GSC logic between the two.
 */
class RunSiteAnalysis
{
    /**
     * GSC's own typical fresh-data window - matches Calendar.php-adjacent
     * conventions of picking a plain, well-understood constant rather than
     * making this configurable before there's a real need to.
     */
    private const KEYWORD_LOOKBACK_DAYS = 28;

    public function __construct(
        private readonly PageCrawler $crawler,
        private readonly PageSpeedClient $pageSpeed,
        private readonly SearchConsoleClient $searchConsole,
    ) {}

    public function execute(SeoWebsite $website): SeoAnalysis
    {
        $crawl = $this->crawler->crawl($website->url);
        $desktop = $this->pageSpeed->analyze($website->url, 'DESKTOP');
        $mobile = $this->pageSpeed->analyze($website->url, 'MOBILE');

        $analysis = SeoAnalysis::create([
            'seo_website_id' => $website->id,
            'analyzed_at' => now(),
            'title' => $crawl['title'],
            'meta_description' => $crawl['meta_description'],
            'h1s' => $crawl['h1s'],
            'h2s' => $crawl['h2s'],
            'desktop_response_ms' => $desktop['response_time_ms'],
            'mobile_response_ms' => $mobile['response_time_ms'],
            'desktop_score' => $desktop['score'],
            'mobile_score' => $mobile['score'],
        ]);

        if ($website->search_console_account_id && $website->search_console_site_url) {
            $this->refreshKeywordMetrics($website);
        }

        return $analysis;
    }

    private function refreshKeywordMetrics(SeoWebsite $website): void
    {
        $endDate = now();
        $startDate = now()->subDays(self::KEYWORD_LOOKBACK_DAYS);

        $rows = $this->searchConsole->queryKeywords(
            $website->searchConsoleAccount,
            $website->search_console_site_url,
            $startDate->toDateString(),
            $endDate->toDateString(),
        );

        // Deletes any rows already pulled for this exact period before
        // inserting fresh ones - re-running analysis the same day
        // otherwise accumulates exact-duplicate rows for an unchanged
        // rolling window instead of replacing them.
        SeoKeywordMetric::where('seo_website_id', $website->id)
            ->where('period_start', $startDate->toDateString())
            ->where('period_end', $endDate->toDateString())
            ->delete();

        if ($rows === []) {
            return;
        }

        $now = now();

        SeoKeywordMetric::insert(array_map(fn (array $row) => [
            'seo_website_id' => $website->id,
            'query' => $row['keys'][0] ?? '',
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'pulled_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows));
    }
}
