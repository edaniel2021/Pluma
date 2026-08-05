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
     * Laravel's HTTP client defaults to a 30s timeout, which is too short
     * for a real Lighthouse audit against a live site - hit for real in
     * production as a raw "cURL error 28: Operation timed out after
     * 30002 milliseconds" calling this exact endpoint. See
     * RunSiteAnalysisJob's own $timeout/WithoutOverlapping/retry_after,
     * which all had to be raised in lockstep with this value - the same
     * four-value nesting requirement documented for the AI Assistant's
     * gpt-image-1 timeout gotcha.
     */
    public const REQUEST_TIMEOUT_SECONDS = 60;

    /**
     * @return array{score: ?int, response_time_ms: ?int}
     */
    public function analyze(string $url, string $strategy): array
    {
        $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)->get(self::BASE_URL, [
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
