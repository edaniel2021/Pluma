<?php

namespace App\Domain\Seo\Support;

use App\Domain\Seo\Models\SearchConsoleAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin Http-facade wrapper for the Search Console API - no official Google
 * API client package is installed (and none is needed for two simple REST
 * calls), same reasoning as FalService/GeminiService using plain Http
 * calls instead of an SDK. Endpoints/scopes verified directly against
 * Google's live API reference before writing this.
 */
class SearchConsoleClient
{
    private const BASE_URL = 'https://www.googleapis.com/webmasters/v3';

    /**
     * Made explicit (was previously an implicit Laravel-default 30s on
     * queryKeywords()/listSites(), and didn't exist at all yet on the new
     * queryPagesForKeyword()) so RunSiteAnalysisJob's and
     * RunKeywordPageAnalysisJob's own timeout nesting math is a real,
     * checkable number - same reasoning as PageSpeedClient's own constant.
     */
    public const REQUEST_TIMEOUT_SECONDS = 30;

    /**
     * Per-keyword page-dimension query only cares about the top-ranking
     * pages for that one keyword, not an exhaustive list - far below the
     * unfiltered query-dimension pull's rowLimit of 1000.
     */
    private const PAGE_QUERY_ROW_LIMIT = 100;

    /**
     * GET /sites - the verified properties this Google account has access
     * to, used by the Websites config page to offer a dropdown instead of
     * a raw text field.
     *
     * @return array<int, array{siteUrl: string, permissionLevel: string}>
     */
    public function listSites(SearchConsoleAccount $account): array
    {
        return $this->request($account)
            ->get(self::BASE_URL.'/sites')
            ->throw()
            ->json('siteEntry', []);
    }

    /**
     * POST /sites/{siteUrl}/searchAnalytics/query - raw query-level
     * performance rows (clicks/impressions/ctr/position) for the given
     * date range. siteUrl must be Google's exact property identifier
     * (e.g. "https://example.com/" or "sc-domain:example.com"), not
     * necessarily the same string as the website's plain url column.
     *
     * @return array<int, array{keys: array<int, string>, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function queryKeywords(SearchConsoleAccount $account, string $siteUrl, string $startDate, string $endDate): array
    {
        return $this->request($account)
            ->post(self::BASE_URL.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['query'],
                'rowLimit' => 1000,
            ])
            ->throw()
            ->json('rows', []);
    }

    /**
     * POST /sites/{siteUrl}/searchAnalytics/query filtered to one exact
     * keyword via dimensionFilterGroups, dimensioned by page - which
     * pages rank for this specific keyword and their
     * clicks/impressions/ctr/position. GSC's filter groups only combine
     * via AND (no OR across groups), which is why this takes one keyword
     * at a time rather than a batch - RunKeywordPageAnalysis calls this
     * once per submitted keyword.
     *
     * @return array<int, array{keys: array<int, string>, clicks: float, impressions: float, ctr: float, position: float}>
     */
    public function queryPagesForKeyword(SearchConsoleAccount $account, string $siteUrl, string $keyword, string $startDate, string $endDate): array
    {
        return $this->request($account)
            ->post(self::BASE_URL.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['page'],
                'dimensionFilterGroups' => [
                    [
                        'filters' => [
                            ['dimension' => 'query', 'operator' => 'equals', 'expression' => $keyword],
                        ],
                    ],
                ],
                'rowLimit' => self::PAGE_QUERY_ROW_LIMIT,
            ])
            ->throw()
            ->json('rows', []);
    }

    private function request(SearchConsoleAccount $account): PendingRequest
    {
        if ($account->needsTokenRefresh()) {
            $this->refreshAccessToken($account);
        }

        return Http::timeout(self::REQUEST_TIMEOUT_SECONDS)->withToken($account->access_token)->acceptJson();
    }

    /**
     * Google access tokens are short-lived (~1 hour) - without this, every
     * connected account would silently stop working shortly after
     * connecting despite access_type=offline having stored a
     * refresh_token specifically to prevent that. Mirrors
     * YouTubeProvider::refreshToken()'s exact manual-POST pattern (no
     * Socialite helper exists for a mid-request refresh like this).
     */
    private function refreshAccessToken(SearchConsoleAccount $account): void
    {
        if (! $account->refresh_token) {
            $account->forceFill(['disabled_at' => now()])->save();

            return;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => config('services.search-console.client_id'),
            'client_secret' => config('services.search-console.client_secret'),
        ]);

        if ($response->failed()) {
            $account->forceFill(['disabled_at' => now()])->save();

            return;
        }

        $account->forceFill([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();
    }
}
