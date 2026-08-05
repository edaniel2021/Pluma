<?php

namespace App\Domain\Seo\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches and parses {url}/robots.txt - a plain, small text file, so no XML
 * parsing and no new Composer dependency (matching PageCrawler/PageSpeedClient's
 * "no heavy SDK" convention). A missing/404 robots.txt is a normal, common
 * `exists: false` result, not a failure - deliberately does not `->throw()`,
 * unlike PageCrawler/PageSpeedClient/SearchConsoleClient where a failed
 * request means the whole analysis failed.
 */
class RobotsTxtAnalyzer
{
    public const REQUEST_TIMEOUT_SECONDS = 15;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * @return array{url: string, exists: bool, http_status: ?int, disallow_rules: array<int, array{user_agent: string, path: string}>, blocks_indexing: bool, sitemap_directives: array<int, string>, parse_error: ?string}
     */
    public function analyze(string $baseUrl): array
    {
        $url = rtrim($baseUrl, '/').'/robots.txt';

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);
        } catch (Throwable $e) {
            return $this->result($url, exists: false, parseError: $e->getMessage());
        }

        if ($response->failed()) {
            return $this->result($url, exists: false, httpStatus: $response->status());
        }

        [$disallowRules, $sitemapDirectives] = $this->parse($response->body());

        $blocksIndexing = collect($disallowRules)
            ->contains(fn (array $rule) => $rule['user_agent'] === '*' && $rule['path'] === '/');

        return $this->result(
            $url,
            exists: true,
            httpStatus: $response->status(),
            disallowRules: $disallowRules,
            blocksIndexing: $blocksIndexing,
            sitemapDirectives: $sitemapDirectives,
        );
    }

    /**
     * Lenient line-parser, not a strict spec implementation - real-world
     * robots.txt files are hand-edited and don't always insert blank-line
     * separators between User-agent groups, so consecutive "User-agent:"
     * lines are treated as one group, and a new group only starts once a
     * Disallow line has been seen for the current group.
     *
     * @return array{0: array<int, array{user_agent: string, path: string}>, 1: array<int, string>}
     */
    private function parse(string $body): array
    {
        $disallowRules = [];
        $sitemapDirectives = [];
        $currentUserAgents = [];
        $groupStarted = false;

        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$directive, $value] = array_map('trim', explode(':', $line, 2));
            $directive = strtolower($directive);

            if ($directive === 'user-agent') {
                if ($groupStarted) {
                    $currentUserAgents = [];
                    $groupStarted = false;
                }
                $currentUserAgents[] = $value;

                continue;
            }

            if ($directive === 'disallow' && $value !== '') {
                foreach ($currentUserAgents ?: ['*'] as $userAgent) {
                    $disallowRules[] = ['user_agent' => $userAgent, 'path' => $value];
                }
                $groupStarted = true;

                continue;
            }

            if ($directive === 'sitemap' && $value !== '') {
                $sitemapDirectives[] = $value;
            }
        }

        return [$disallowRules, $sitemapDirectives];
    }

    /**
     * @param  array<int, array{user_agent: string, path: string}>  $disallowRules
     * @param  array<int, string>  $sitemapDirectives
     * @return array{url: string, exists: bool, http_status: ?int, disallow_rules: array<int, array{user_agent: string, path: string}>, blocks_indexing: bool, sitemap_directives: array<int, string>, parse_error: ?string}
     */
    private function result(
        string $url,
        bool $exists,
        ?int $httpStatus = null,
        array $disallowRules = [],
        bool $blocksIndexing = false,
        array $sitemapDirectives = [],
        ?string $parseError = null,
    ): array {
        return [
            'url' => $url,
            'exists' => $exists,
            'http_status' => $httpStatus,
            'disallow_rules' => $disallowRules,
            'blocks_indexing' => $blocksIndexing,
            'sitemap_directives' => $sitemapDirectives,
            'parse_error' => $parseError,
        ];
    }
}
