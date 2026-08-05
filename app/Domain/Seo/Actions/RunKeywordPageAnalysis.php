<?php

namespace App\Domain\Seo\Actions;

use App\Domain\Seo\Models\SeoKeywordPageRank;
use App\Domain\Seo\Models\SeoPageAnalysis;
use App\Domain\Seo\Models\SeoWebsite;
use App\Domain\Seo\Support\PageCrawler;
use App\Domain\Seo\Support\SearchConsoleClient;
use InvalidArgumentException;
use Throwable;

/**
 * Orchestrates the keyword-driven page discovery + on-page analysis flow:
 * for each submitted keyword, ask Search Console which pages rank for it
 * (dimensioned by page, filtered to that exact query), then crawl the
 * capped top subset of the resulting unique pages. Deliberately separate
 * from RunSiteAnalysis - this is optional, user-input-driven, and can
 * legitimately take much longer (see RunKeywordPageAnalysisJob's own
 * queue connection).
 */
class RunKeywordPageAnalysis
{
    /**
     * Matches RunSiteAnalysis::KEYWORD_LOOKBACK_DAYS - GSC's own typical
     * fresh-data window.
     */
    private const KEYWORD_LOOKBACK_DAYS = 28;

    public const MAX_KEYWORDS_PER_SUBMISSION = 15;

    /**
     * Caps PageCrawler calls per run - if the unique-page set from all
     * submitted keywords exceeds this, only the top pages by aggregate
     * clicks are actually crawled; the rest are recorded as rank-only rows
     * (crawled_at null) rather than silently dropped.
     */
    public const MAX_UNIQUE_PAGES_CRAWLED = 25;

    public function __construct(
        private readonly SearchConsoleClient $searchConsole,
        private readonly PageCrawler $crawler,
    ) {}

    /**
     * @param  array<int, string>  $keywords
     */
    public function execute(SeoWebsite $website, array $keywords): void
    {
        if (! $website->search_console_account_id || ! $website->search_console_site_url) {
            throw new InvalidArgumentException('This website is not mapped to a Search Console property.');
        }

        $keywords = $this->normalizeKeywords($keywords);

        if ($keywords === []) {
            throw new InvalidArgumentException('At least one keyword is required.');
        }

        $endDate = now();
        $startDate = now()->subDays(self::KEYWORD_LOOKBACK_DAYS);

        $pageData = $this->collectPageData($website, $keywords, $startDate->toDateString(), $endDate->toDateString());

        // Delete-and-replace prior results for this website - cascades to
        // seo_keyword_page_ranks via FK. Matches SeoKeywordMetric's
        // existing replace-not-accumulate pattern: there's no
        // trend-over-time feature requested for this data, so unbounded
        // history has no read path yet.
        SeoPageAnalysis::where('seo_website_id', $website->id)->delete();

        $this->persist($website, $pageData, $keywords, $startDate->toDateString(), $endDate->toDateString());
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<string, array{total_clicks: int, rows: array<int, array{keyword: string, clicks: int, impressions: int, ctr: float, position: float}>}>
     */
    private function collectPageData(SeoWebsite $website, array $keywords, string $startDate, string $endDate): array
    {
        $pageData = [];

        foreach ($keywords as $keyword) {
            $rows = $this->searchConsole->queryPagesForKeyword(
                $website->searchConsoleAccount,
                $website->search_console_site_url,
                $keyword,
                $startDate,
                $endDate,
            );

            foreach ($rows as $row) {
                $pageUrl = $row['keys'][0] ?? null;

                if (! $pageUrl) {
                    continue;
                }

                $clicks = (int) ($row['clicks'] ?? 0);

                $pageData[$pageUrl]['rows'][] = [
                    'keyword' => $keyword,
                    'clicks' => $clicks,
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                ];
                $pageData[$pageUrl]['total_clicks'] = ($pageData[$pageUrl]['total_clicks'] ?? 0) + $clicks;
            }
        }

        return $pageData;
    }

    /**
     * @param  array<string, array{total_clicks: int, rows: array<int, array{keyword: string, clicks: int, impressions: int, ctr: float, position: float}>}>  $pageData
     * @param  array<int, string>  $keywords
     */
    private function persist(SeoWebsite $website, array $pageData, array $keywords, string $startDate, string $endDate): void
    {
        if ($pageData === []) {
            $website->update([
                'last_keyword_check_keywords' => $keywords,
                'last_keyword_analysis_completed_at' => now(),
                'last_keyword_analysis_failed_at' => null,
                'last_keyword_analysis_error' => null,
            ]);

            return;
        }

        $pageUrls = array_keys($pageData);
        // Sort by aggregate clicks desc, URL asc as a deterministic
        // tiebreaker (both for tests and for stable output across runs).
        usort($pageUrls, fn (string $a, string $b) => $pageData[$b]['total_clicks'] <=> $pageData[$a]['total_clicks'] ?: $a <=> $b);

        $toCrawl = array_slice($pageUrls, 0, self::MAX_UNIQUE_PAGES_CRAWLED);
        $now = now();

        foreach ($pageUrls as $pageUrl) {
            $crawlResult = null;
            $crawlError = null;

            if (in_array($pageUrl, $toCrawl, true)) {
                try {
                    $crawlResult = $this->crawler->crawl($pageUrl);
                } catch (Throwable $e) {
                    // One page's 403/timeout must not discard the other
                    // pages' results or any rank rows - deliberate
                    // divergence from RunSiteAnalysis's homepage crawl,
                    // where a failure is allowed to fail the whole run.
                    $crawlError = $e->getMessage();
                }
            }

            $pageAnalysis = SeoPageAnalysis::create([
                'seo_website_id' => $website->id,
                'page_url' => $pageUrl,
                'title' => $crawlResult['title'] ?? null,
                'meta_description' => $crawlResult['meta_description'] ?? null,
                'h1s' => $crawlResult['h1s'] ?? null,
                'h2s' => $crawlResult['h2s'] ?? null,
                'crawled_at' => $crawlResult !== null ? $now : null,
                'crawl_error' => $crawlError,
            ]);

            foreach ($pageData[$pageUrl]['rows'] as $row) {
                SeoKeywordPageRank::create([
                    'seo_website_id' => $website->id,
                    'seo_page_analysis_id' => $pageAnalysis->id,
                    'keyword' => $row['keyword'],
                    'clicks' => $row['clicks'],
                    'impressions' => $row['impressions'],
                    'ctr' => $row['ctr'],
                    'position' => $row['position'],
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'pulled_at' => $now,
                ]);
            }
        }

        $website->update([
            'last_keyword_check_keywords' => $keywords,
            'last_keyword_analysis_completed_at' => now(),
            'last_keyword_analysis_failed_at' => null,
            'last_keyword_analysis_error' => null,
        ]);
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function normalizeKeywords(array $keywords): array
    {
        $normalized = collect($keywords)
            ->map(fn ($keyword) => strtolower(trim((string) $keyword)))
            ->filter(fn (string $keyword) => $keyword !== '')
            ->unique()
            ->values()
            ->all();

        return array_slice($normalized, 0, self::MAX_KEYWORDS_PER_SUBMISSION);
    }
}
