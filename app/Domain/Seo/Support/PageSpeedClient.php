<?php

namespace App\Domain\Seo\Support;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper for the PageSpeed Insights API v5 - a plain API key, no
 * OAuth (unlike Search Console), verified directly against Google's live
 * API reference before writing this. "strategy" is 'DESKTOP' or 'MOBILE' -
 * these are two separate calls, not a single combined response.
 */
class PageSpeedClient
{
    private const BASE_URL = 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * @return array{score: ?int, response_time_ms: ?int}
     */
    public function analyze(string $url, string $strategy): array
    {
        $response = Http::get(self::BASE_URL, [
            'url' => $url,
            'strategy' => $strategy,
            'key' => config('services.pagespeed.key'),
        ])->throw();

        $score = $response->json('lighthouseResult.categories.performance.score');
        $responseTimeMs = $response->json('lighthouseResult.audits.server-response-time.numericValue');

        return [
            // Lighthouse reports this as a 0-1 fraction; the rest of this
            // app's UI (and the seo_analyses columns) expect a 0-100 score.
            'score' => $score !== null ? (int) round($score * 100) : null,
            'response_time_ms' => $responseTimeMs !== null ? (int) round($responseTimeMs) : null,
        ];
    }
}
