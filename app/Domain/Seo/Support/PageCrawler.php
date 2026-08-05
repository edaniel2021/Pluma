<?php

namespace App\Domain\Seo\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;

/**
 * Fetches a single URL (homepage-only crawl scope for now - no
 * link-following/sitemap crawling) and extracts title/meta
 * description/H1s/H2s using native DOMDocument/DOMXPath. No new Composer
 * dependency (e.g. symfony/dom-crawler) - the extraction need is simple
 * enough that the built-in extension covers it, matching this codebase's
 * general preference for plain PHP over a full library where the need is
 * this small.
 */
class PageCrawler
{
    /**
     * Laravel's HTTP client sends a bare "GuzzleHttp/x" User-Agent by
     * default, which many real sites' WAF/nginx bot-blocking rules reject
     * outright with a 403 - hit for real in production analyzing a live
     * site. A realistic browser User-Agent is standard practice for this
     * kind of crawl (the site owner is analyzing their own site), not an
     * attempt to evade any legitimate protection.
     */
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * Made explicit (was previously an implicit Laravel-default 30s) so
     * RunSiteAnalysisJob's and RunKeywordPageAnalysisJob's own timeout
     * nesting math is a real, checkable number rather than "whatever
     * Laravel defaults to today" - same reasoning as
     * PageSpeedClient::REQUEST_TIMEOUT_SECONDS.
     */
    public const REQUEST_TIMEOUT_SECONDS = 30;

    /**
     * @return array{title: ?string, meta_description: ?string, h1s: array<int, string>, h2s: array<int, string>}
     */
    public function crawl(string $url): array
    {
        $html = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get($url)->throw()->body();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $title = $xpath->query('//title')?->item(0)?->textContent;

        $metaDescription = null;
        foreach ($xpath->query('//meta[@name="description"]') ?: [] as $meta) {
            $metaDescription = $meta->getAttribute('content');
            break;
        }

        return [
            'title' => $title !== null ? trim($title) : null,
            'meta_description' => $metaDescription !== null ? trim($metaDescription) : null,
            'h1s' => $this->textOf($xpath, '//h1'),
            'h2s' => $this->textOf($xpath, '//h2'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function textOf(DOMXPath $xpath, string $query): array
    {
        $texts = [];

        foreach ($xpath->query($query) ?: [] as $node) {
            $texts[] = trim($node->textContent);
        }

        return $texts;
    }
}
