<?php

namespace App\Domain\Seo\Actions;

use App\Domain\Seo\Support\RobotsTxtAnalyzer;
use App\Domain\Seo\Support\SitemapAnalyzer;
use Throwable;

/**
 * Orchestrates the always-on robots.txt + sitemap.xml checks folded into
 * RunSiteAnalysis::execute() - discovers the sitemap URL from robots.txt's
 * first `Sitemap:` directive, falling back to {url}/sitemap.xml when none
 * is declared (or robots.txt itself doesn't exist).
 */
class AnalyzeRobotsAndSitemap
{
    public function __construct(
        private readonly RobotsTxtAnalyzer $robotsTxt,
        private readonly SitemapAnalyzer $sitemap,
    ) {}

    /**
     * @return array{robots_txt_result: array<string, mixed>, sitemap_result: array<string, mixed>}
     */
    public function execute(string $url): array
    {
        $robotsResult = $this->robotsTxt->analyze($url);

        $declaredSitemapUrl = $robotsResult['sitemap_directives'][0] ?? null;
        $sitemapUrl = $declaredSitemapUrl ?? rtrim($url, '/').'/sitemap.xml';
        $source = $declaredSitemapUrl ? 'robots_txt' : 'default_fallback';

        try {
            $sitemapResult = array_merge($this->sitemap->analyze($sitemapUrl), ['source' => $source]);
        } catch (Throwable $e) {
            $sitemapResult = [
                'url' => $sitemapUrl,
                'source' => $source,
                'exists' => false,
                'is_index' => false,
                'url_count' => null,
                'child_sitemap_count' => null,
                'parse_error' => $e->getMessage(),
            ];
        }

        return [
            'robots_txt_result' => $robotsResult,
            'sitemap_result' => $sitemapResult,
        ];
    }
}
