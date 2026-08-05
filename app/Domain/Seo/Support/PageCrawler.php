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
     * @return array{title: ?string, meta_description: ?string, h1s: array<int, string>, h2s: array<int, string>}
     */
    public function crawl(string $url): array
    {
        $html = Http::get($url)->throw()->body();

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
