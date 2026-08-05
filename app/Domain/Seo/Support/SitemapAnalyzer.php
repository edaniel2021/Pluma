<?php

namespace App\Domain\Seo\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches and parses a sitemap.xml (or sitemap index) via native
 * DOMDocument/DOMXPath, same idiom as PageCrawler - no new Composer
 * dependency. A sitemap index's child sitemaps are deliberately NOT
 * recursively fetched here (that would silently reopen the "sitemaps can
 * list thousands of URLs" problem this feature exists to avoid, just one
 * level up) - only child_sitemap_count is reported for an index; a real
 * url_count is only produced for a plain <urlset>. A missing/broken
 * sitemap is a normal, common finding, not a failure - deliberately does
 * not `->throw()`.
 */
class SitemapAnalyzer
{
    public const REQUEST_TIMEOUT_SECONDS = 15;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * @return array{url: string, exists: bool, is_index: bool, url_count: ?int, child_sitemap_count: ?int, parse_error: ?string}
     */
    public function analyze(string $sitemapUrl): array
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($sitemapUrl);
        } catch (Throwable $e) {
            return $this->result($sitemapUrl, exists: false, parseError: $e->getMessage());
        }

        if ($response->failed()) {
            return $this->result($sitemapUrl, exists: false);
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($response->body());
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();

        if (! $loaded || ! $dom->documentElement) {
            $message = $xmlErrors[0]->message ?? 'Could not parse sitemap XML.';

            return $this->result($sitemapUrl, exists: true, parseError: trim($message));
        }

        $xpath = new DOMXPath($dom);
        $isIndex = strtolower($dom->documentElement->localName ?? '') === 'sitemapindex';

        if ($isIndex) {
            $childCount = $xpath->query('//*[local-name()="sitemap"]')->length;

            return $this->result($sitemapUrl, exists: true, isIndex: true, childSitemapCount: $childCount);
        }

        $urlCount = $xpath->query('//*[local-name()="url"]')->length;

        return $this->result($sitemapUrl, exists: true, isIndex: false, urlCount: $urlCount);
    }

    /**
     * @return array{url: string, exists: bool, is_index: bool, url_count: ?int, child_sitemap_count: ?int, parse_error: ?string}
     */
    private function result(
        string $url,
        bool $exists,
        bool $isIndex = false,
        ?int $urlCount = null,
        ?int $childSitemapCount = null,
        ?string $parseError = null,
    ): array {
        return [
            'url' => $url,
            'exists' => $exists,
            'is_index' => $isIndex,
            'url_count' => $urlCount,
            'child_sitemap_count' => $childSitemapCount,
            'parse_error' => $parseError,
        ];
    }
}
