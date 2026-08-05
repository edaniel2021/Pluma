<?php

namespace Tests\Feature;

use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Seo\Actions\AnalyzeRobotsAndSitemap;
use App\Domain\Seo\Actions\ComputeTrendingKeywords;
use App\Domain\Seo\Actions\ConnectSearchConsole;
use App\Domain\Seo\Actions\RunKeywordPageAnalysis;
use App\Domain\Seo\Actions\RunSiteAnalysis;
use App\Domain\Seo\Jobs\RunKeywordPageAnalysisJob;
use App\Domain\Seo\Jobs\RunSiteAnalysisJob;
use App\Domain\Seo\Models\SearchConsoleAccount;
use App\Domain\Seo\Models\SeoAnalysis;
use App\Domain\Seo\Models\SeoKeywordMetric;
use App\Domain\Seo\Models\SeoKeywordPageRank;
use App\Domain\Seo\Models\SeoPageAnalysis;
use App\Domain\Seo\Models\SeoWebsite;
use App\Domain\Seo\Support\PageCrawler;
use App\Domain\Seo\Support\PageSpeedClient;
use App\Domain\Seo\Support\RobotsTxtAnalyzer;
use App\Domain\Seo\Support\SearchConsoleClient;
use App\Domain\Seo\Support\SitemapAnalyzer;
use App\Livewire\Seo\Analysis;
use App\Livewire\Seo\Websites;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // ConnectSearchConsole
    // ---------------------------------------------------------------

    public function test_connect_search_console_creates_an_account(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);

        $socialiteUser = SocialiteUser::fake([
            'email' => 'seo@example.com',
            'token' => 'access-token-1',
            'refreshToken' => 'refresh-token-1',
            'expiresIn' => 3600,
        ]);

        $account = app(ConnectSearchConsole::class)->execute($socialiteUser);

        $this->assertSame('seo@example.com', $account->google_email);
        $this->assertSame('access-token-1', $account->access_token);
        $this->assertSame('refresh-token-1', $account->refresh_token);
        $this->assertNotNull($account->token_expires_at);
        $this->assertNull($account->disabled_at);

        CurrentOrganization::clear();
    }

    public function test_connect_search_console_updates_the_existing_account_for_the_same_email(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);

        app(ConnectSearchConsole::class)->execute(SocialiteUser::fake(['email' => 'seo@example.com', 'token' => 'old-token']));
        app(ConnectSearchConsole::class)->execute(SocialiteUser::fake(['email' => 'seo@example.com', 'token' => 'new-token']));

        $this->assertSame(1, SearchConsoleAccount::count());
        $this->assertSame('new-token', SearchConsoleAccount::first()->access_token);

        CurrentOrganization::clear();
    }

    // ---------------------------------------------------------------
    // PageCrawler
    // ---------------------------------------------------------------

    public function test_page_crawler_extracts_title_meta_h1s_and_h2s(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>  Example Page  </title>
                        <meta name="description" content="An example description.">
                    </head>
                    <body>
                        <h1>Main Heading</h1>
                        <h2>First subheading</h2>
                        <h2>Second subheading</h2>
                    </body>
                </html>
                HTML, 200),
        ]);

        $result = app(PageCrawler::class)->crawl('https://example.com/');

        $this->assertSame('Example Page', $result['title']);
        $this->assertSame('An example description.', $result['meta_description']);
        $this->assertSame(['Main Heading'], $result['h1s']);
        $this->assertSame(['First subheading', 'Second subheading'], $result['h2s']);
    }

    public function test_page_crawler_handles_a_page_with_no_h1s_or_meta_description(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response('<html><head><title>Bare Page</title></head><body></body></html>', 200),
        ]);

        $result = app(PageCrawler::class)->crawl('https://example.com/');

        $this->assertSame('Bare Page', $result['title']);
        $this->assertNull($result['meta_description']);
        $this->assertSame([], $result['h1s']);
        $this->assertSame([], $result['h2s']);
    }

    /**
     * Regression test for a real staging failure: crawling a live site
     * returned a genuine "403 Forbidden" from nginx - Laravel's HTTP
     * client sends a bare "GuzzleHttp/x" User-Agent by default, which the
     * site's bot-blocking rules rejected outright. A realistic browser
     * User-Agent fixed it.
     */
    public function test_page_crawler_sends_a_realistic_browser_user_agent(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response('<html><head><title>Example</title></head></html>', 200),
        ]);

        app(PageCrawler::class)->crawl('https://example.com/');

        Http::assertSent(fn ($request) => str($request->header('User-Agent')[0] ?? '')->contains('Mozilla/5.0'));
    }

    // ---------------------------------------------------------------
    // PageSpeedClient
    // ---------------------------------------------------------------

    public function test_pagespeed_client_extracts_score_and_response_time(): void
    {
        config(['services.pagespeed.key' => 'test-pagespeed-key']);

        Http::fake([
            'pagespeedonline.googleapis.com/*' => Http::response([
                'lighthouseResult' => [
                    'categories' => ['performance' => ['score' => 0.87]],
                    'audits' => ['server-response-time' => ['numericValue' => 245.6]],
                ],
            ], 200),
        ]);

        $result = app(PageSpeedClient::class)->analyze('https://example.com/', 'DESKTOP');

        $this->assertSame(87, $result['score']);
        $this->assertSame(246, $result['response_time_ms']);

        Http::assertSent(fn ($request) => str($request->url())->contains('pagespeedonline.googleapis.com')
            && $request['strategy'] === 'DESKTOP'
            && $request['key'] === 'test-pagespeed-key');
    }

    // ---------------------------------------------------------------
    // SearchConsoleClient
    // ---------------------------------------------------------------

    public function test_search_console_client_lists_sites(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites' => Http::response([
                'siteEntry' => [
                    ['siteUrl' => 'https://example.com/', 'permissionLevel' => 'siteOwner'],
                ],
            ], 200),
        ]);

        $account = SearchConsoleAccount::factory()->make(['access_token' => 'test-token', 'token_expires_at' => now()->addHour()]);

        $sites = app(SearchConsoleClient::class)->listSites($account);

        $this->assertSame('https://example.com/', $sites[0]['siteUrl']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_search_console_client_queries_keywords(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    ['keys' => ['wheelchair kuwait'], 'clicks' => 12, 'impressions' => 100, 'ctr' => 0.12, 'position' => 4.5],
                ],
            ], 200),
        ]);

        $account = SearchConsoleAccount::factory()->make(['access_token' => 'test-token', 'token_expires_at' => now()->addHour()]);

        $rows = app(SearchConsoleClient::class)->queryKeywords($account, 'https://example.com/', '2026-07-01', '2026-07-28');

        $this->assertSame('wheelchair kuwait', $rows[0]['keys'][0]);
        Http::assertSent(function ($request) {
            return str($request->url())->contains('searchAnalytics/query')
                && $request->data()['startDate'] === '2026-07-01'
                && $request->data()['dimensions'] === ['query'];
        });
    }

    /**
     * Regression test for a real staging bug: a genuine
     * "403 ACCESS_TOKEN_SCOPE_INSUFFICIENT" from Google (the OAuth consent
     * screen's configured scopes didn't actually include
     * webmasters.readonly, despite the app requesting it at connect time)
     * was completely indistinguishable from "this account has zero
     * verified properties" - listSites()/queryKeywords() never called
     * throw(), so a failed response's missing "siteEntry"/"rows" key just
     * silently fell back to the empty-array default.
     */
    public function test_search_console_client_throws_on_a_failed_list_sites_response(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites' => Http::response([
                'error' => ['code' => 403, 'message' => 'Request had insufficient authentication scopes.'],
            ], 403),
        ]);

        $account = SearchConsoleAccount::factory()->make(['access_token' => 'test-token', 'token_expires_at' => now()->addHour()]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        app(SearchConsoleClient::class)->listSites($account);
    }

    public function test_search_console_client_throws_on_a_failed_query_keywords_response(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['error' => ['code' => 403]], 403),
        ]);

        $account = SearchConsoleAccount::factory()->make(['access_token' => 'test-token', 'token_expires_at' => now()->addHour()]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        app(SearchConsoleClient::class)->queryKeywords($account, 'https://example.com/', '2026-07-01', '2026-07-28');
    }

    public function test_search_console_client_refreshes_an_expired_token_before_calling(): void
    {
        config(['services.search-console.client_id' => 'test-client-id', 'services.search-console.client_secret' => 'test-secret']);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'refreshed-token', 'expires_in' => 3600], 200),
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => []], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create([
            'access_token' => 'stale-token',
            'refresh_token' => 'a-refresh-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        app(SearchConsoleClient::class)->listSites($account);

        Http::assertSent(fn ($request) => str($request->url())->contains('oauth2.googleapis.com/token')
            && $request->data()['refresh_token'] === 'a-refresh-token');
        Http::assertSent(fn ($request) => str($request->url())->contains('webmasters/v3/sites')
            && $request->hasHeader('Authorization', 'Bearer refreshed-token'));

        $this->assertSame('refreshed-token', $account->fresh()->access_token);

        CurrentOrganization::clear();
    }

    public function test_search_console_client_disables_the_account_when_refresh_fails(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([], 400),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create([
            'refresh_token' => 'a-refresh-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        try {
            app(SearchConsoleClient::class)->listSites($account);
        } catch (\Throwable) {
            // The subsequent API call may itself fail once withToken() gets
            // a stale/empty token - what matters here is disabled_at.
        }

        $this->assertNotNull($account->fresh()->disabled_at);

        CurrentOrganization::clear();
    }

    // ---------------------------------------------------------------
    // RunSiteAnalysis
    // ---------------------------------------------------------------

    public function test_run_site_analysis_persists_a_snapshot_without_a_mapped_gsc_property(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><title>Example</title></head><body><h1>Hi</h1></body></html>', 200),
            'pagespeedonline.googleapis.com/*' => Http::response([
                'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]], 'audits' => ['server-response-time' => ['numericValue' => 100]]],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create(['url' => 'https://example.com/']);

        $analysis = app(RunSiteAnalysis::class)->execute($website);

        $this->assertSame('Example', $analysis->title);
        $this->assertSame(['Hi'], $analysis->h1s);
        $this->assertSame(90, $analysis->desktop_score);
        $this->assertSame(0, SeoKeywordMetric::count());

        CurrentOrganization::clear();
    }

    /**
     * Regression test for a real staging failure: a real site's actual
     * meta description exceeded 255 characters and hit
     * "SQLSTATE[22001]: String data, right truncated ... value too long
     * for type character varying(255)" - title/meta_description are now
     * `text` columns, not `string` (varchar(255)), since there's no
     * legitimate reason to truncate real crawled content (an overly-long
     * meta description is itself a useful SEO finding to surface, not
     * something to silently cut).
     */
    public function test_run_site_analysis_preserves_a_title_and_meta_description_longer_than_255_characters(): void
    {
        $longTitle = str_repeat('A very long keyword-stuffed page title ', 8);
        $longMetaDescription = str_repeat('An unusually long meta description some real sites actually write. ', 5);
        $this->assertGreaterThan(255, strlen($longTitle));
        $this->assertGreaterThan(255, strlen($longMetaDescription));

        Http::fake([
            'example.com/*' => Http::response(
                '<html><head><title>'.$longTitle.'</title><meta name="description" content="'.$longMetaDescription.'"></head></html>',
                200
            ),
            'pagespeedonline.googleapis.com/*' => Http::response([
                'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]], 'audits' => []],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create(['url' => 'https://example.com/']);

        $analysis = app(RunSiteAnalysis::class)->execute($website);

        $this->assertSame(trim($longTitle), $analysis->title);
        $this->assertSame(trim($longMetaDescription), $analysis->meta_description);

        CurrentOrganization::clear();
    }

    public function test_run_site_analysis_refreshes_keyword_metrics_when_mapped_to_a_gsc_property(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><title>Example</title></head><body></body></html>', 200),
            'pagespeedonline.googleapis.com/*' => Http::response([
                'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]], 'audits' => []],
            ], 200),
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    ['keys' => ['wheelchair kuwait'], 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 3.2],
                ],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'url' => 'https://example.com/',
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        app(RunSiteAnalysis::class)->execute($website);

        $this->assertSame(1, SeoKeywordMetric::count());
        $this->assertSame('wheelchair kuwait', SeoKeywordMetric::first()->query);
        $this->assertSame(5, SeoKeywordMetric::first()->clicks);

        CurrentOrganization::clear();
    }

    /**
     * Regression guard: re-running analysis for the same rolling window
     * (the common case - clicking "Run analysis now" twice the same day)
     * must replace the previous keyword rows, not accumulate duplicates
     * alongside them.
     */
    public function test_run_site_analysis_replaces_keyword_metrics_for_the_same_period_instead_of_duplicating(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><title>Example</title></head><body></body></html>', 200),
            'pagespeedonline.googleapis.com/*' => Http::response([
                'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]], 'audits' => []],
            ], 200),
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    ['keys' => ['wheelchair kuwait'], 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 3.2],
                ],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'url' => 'https://example.com/',
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        $runSiteAnalysis = app(RunSiteAnalysis::class);
        $runSiteAnalysis->execute($website);
        $runSiteAnalysis->execute($website);

        $this->assertSame(1, SeoKeywordMetric::count());

        CurrentOrganization::clear();
    }

    // ---------------------------------------------------------------
    // ComputeTrendingKeywords
    // ---------------------------------------------------------------

    public function test_compute_trending_keywords_returns_empty_with_fewer_than_two_periods(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        SeoKeywordMetric::factory()->create(['seo_website_id' => $website->id]);

        $result = app(ComputeTrendingKeywords::class)->execute($website);

        $this->assertTrue($result->isEmpty());

        CurrentOrganization::clear();
    }

    public function test_compute_trending_keywords_computes_click_deltas_between_the_two_most_recent_periods(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();

        SeoKeywordMetric::factory()->create([
            'seo_website_id' => $website->id,
            'query' => 'wheelchair kuwait',
            'clicks' => 5,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-28',
        ]);
        SeoKeywordMetric::factory()->create([
            'seo_website_id' => $website->id,
            'query' => 'wheelchair kuwait',
            'clicks' => 20,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-28',
        ]);
        SeoKeywordMetric::factory()->create([
            'seo_website_id' => $website->id,
            'query' => 'new keyword',
            'clicks' => 8,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-28',
        ]);

        $trending = app(ComputeTrendingKeywords::class)->execute($website);

        $top = $trending->first();
        $this->assertSame('wheelchair kuwait', $top['query']);
        $this->assertSame(20, $top['clicks']);
        $this->assertSame(5, $top['previous_clicks']);
        $this->assertSame(15, $top['delta']);

        $newKeyword = $trending->firstWhere('query', 'new keyword');
        $this->assertSame(0, $newKeyword['previous_clicks']);
        $this->assertSame(8, $newKeyword['delta']);

        CurrentOrganization::clear();
    }

    // ---------------------------------------------------------------
    // RunSiteAnalysisJob
    // ---------------------------------------------------------------

    public function test_run_site_analysis_job_runs_the_analysis_for_the_given_website(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><title>Example</title></head><body></body></html>', 200),
            'pagespeedonline.googleapis.com/*' => Http::response([
                'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]], 'audits' => []],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create(['url' => 'https://example.com/']);
        CurrentOrganization::clear();

        (new RunSiteAnalysisJob($website->id))->handle(app(RunSiteAnalysis::class));

        $this->assertSame(1, SeoAnalysis::count());
    }

    public function test_run_site_analysis_job_is_a_no_op_for_a_deleted_website(): void
    {
        (new RunSiteAnalysisJob(999999))->handle(app(RunSiteAnalysis::class));

        $this->assertSame(0, SeoAnalysis::count());
    }

    /**
     * Regression test for a real staging bug: RunSiteAnalysisJob had no
     * failed() handler at all, so a permanent failure (e.g. the target
     * site returning a real 403) left no trace anywhere the UI could see -
     * the "Run analysis now" button stayed stuck on "Analyzing..."
     * forever, since Analysis::getIsWaitingProperty() only knew how to
     * detect a *successful* fresh SeoAnalysis row.
     */
    public function test_run_site_analysis_job_records_the_failure_on_the_website(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        (new RunSiteAnalysisJob($website->id))->failed(new \Exception('403 Forbidden'));

        $website->refresh();
        $this->assertNotNull($website->last_analysis_failed_at);
        $this->assertSame('403 Forbidden', $website->last_analysis_error);
    }

    public function test_run_site_analysis_job_clears_a_prior_failure_once_it_succeeds(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><title>Example</title></head></html>', 200),
            'pagespeedonline.googleapis.com/*' => Http::response([
                'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]], 'audits' => []],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create([
            'url' => 'https://example.com/',
            'last_analysis_failed_at' => now()->subMinute(),
            'last_analysis_error' => 'a previous failure',
        ]);
        CurrentOrganization::clear();

        (new RunSiteAnalysisJob($website->id))->handle(app(RunSiteAnalysis::class));

        $website->refresh();
        $this->assertNull($website->last_analysis_failed_at);
        $this->assertNull($website->last_analysis_error);
    }

    /**
     * Regression test for a real staging failure: "cURL error 28:
     * Operation timed out after 30002 milliseconds" calling PageSpeed
     * Insights - Laravel's HTTP client defaults to a 30s timeout, too
     * short for a real Lighthouse audit against a live site. Same class
     * of bug (and same fix shape) as the AI Assistant's gpt-image-1
     * timeout gotcha: raising the HTTP timeout alone is pointless unless
     * the job's own $timeout, its WithoutOverlapping lock expiry, and the
     * queue's retry_after all nest above it too.
     *
     * Updated when robots.txt/sitemap checks were folded into this same
     * job (SEO Phase 9d): the real worst-case sequential cost is now the
     * *sum* of every call this job makes in one attempt - PageCrawler +
     * RobotsTxtAnalyzer + SitemapAnalyzer + two PageSpeedClient calls + an
     * optional Search Console query - not just "twice the PageSpeed
     * timeout." The looser previous assertion would have kept passing
     * even if the job timeout were too short for the two new fetches.
     */
    public function test_timeout_values_nest_correctly_for_slow_pagespeed_calls(): void
    {
        $worstCaseSeconds = PageCrawler::REQUEST_TIMEOUT_SECONDS
            + RobotsTxtAnalyzer::REQUEST_TIMEOUT_SECONDS
            + SitemapAnalyzer::REQUEST_TIMEOUT_SECONDS
            + (2 * PageSpeedClient::REQUEST_TIMEOUT_SECONDS)
            + SearchConsoleClient::REQUEST_TIMEOUT_SECONDS;

        $job = new RunSiteAnalysisJob(1);
        $jobTimeout = $job->timeout;
        $lockExpiresAfter = $job->middleware()[0]->expiresAfter;
        $queueRetryAfter = config('queue.connections.redis.retry_after');

        $this->assertGreaterThan($worstCaseSeconds, $jobTimeout,
            "job timeout ({$jobTimeout}s) must exceed the worst-case sum of every call this job makes ({$worstCaseSeconds}s: crawl + robots.txt + sitemap + 2x PageSpeed + GSC query)");
        $this->assertGreaterThan($jobTimeout, $lockExpiresAfter,
            "WithoutOverlapping's expiresAfter ({$lockExpiresAfter}s) must exceed the job timeout ({$jobTimeout}s) or the lock can expire mid-run and let a duplicate job start");
        $this->assertGreaterThan($jobTimeout, $queueRetryAfter,
            "queue retry_after ({$queueRetryAfter}s) must exceed the job timeout ({$jobTimeout}s) or a still-running job can get requeued onto a second worker");
    }

    // ---------------------------------------------------------------
    // Livewire: Websites
    // ---------------------------------------------------------------

    public function test_websites_component_adds_and_removes_a_website(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)->test(Websites::class)
            ->set('url', 'https://example.com')
            ->call('addWebsite')
            ->assertHasNoErrors();

        $website = SeoWebsite::first();
        $this->assertNotNull($website);
        $this->assertSame('https://example.com', $website->url);

        Livewire::actingAs($user)->test(Websites::class)
            ->call('removeWebsite', $website->id);

        $this->assertNull($website->fresh());
    }

    public function test_websites_component_shows_connect_prompt_when_no_search_console_account_exists(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)->test(Websites::class)
            ->assertSee('Connect Google Search Console');
    }

    public function test_websites_component_shows_connected_status_and_can_disconnect(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['google_email' => 'seo@example.com']);
        CurrentOrganization::clear();

        Http::fake(['www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => []], 200)]);

        Livewire::actingAs($user)->test(Websites::class)
            ->assertSee('seo@example.com')
            ->call('disconnectSearchConsole', $account->id);

        $this->assertNull($account->fresh());
    }

    /**
     * Regression test for a real staging bug: a genuine 403
     * ACCESS_TOKEN_SCOPE_INSUFFICIENT from Google looked exactly like "no
     * properties" with zero explanation - a connected account that
     * demonstrably had real Search Console access (confirmed by logging
     * into Search Console directly) appeared totally unmapped with no clue
     * why. The failure is now surfaced instead of silently swallowed.
     */
    public function test_websites_component_surfaces_a_search_console_api_failure_instead_of_silently_showing_empty(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        SearchConsoleAccount::factory()->create(['google_email' => 'seo@example.com']);
        CurrentOrganization::clear();

        Http::fake(['www.googleapis.com/webmasters/v3/sites' => Http::response([
            'error' => ['message' => 'Request had insufficient authentication scopes.'],
        ], 403)]);

        Livewire::actingAs($user)->test(Websites::class)
            ->assertSee('Request had insufficient authentication scopes.');
    }

    /**
     * Real product gap: mapping a website to a Search Console property
     * only ever happened on the "Add Website" form, at creation time -
     * there was no way to go back and map an already-created website
     * afterward (e.g. one added before Search Console was connected)
     * short of deleting and recreating it, which would also wipe its
     * whole analysis history.
     */
    public function test_websites_component_can_map_an_existing_unmapped_website(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create();
        $website = SeoWebsite::factory()->create(['url' => 'https://example.com']);
        CurrentOrganization::clear();

        Http::fake(['www.googleapis.com/webmasters/v3/sites' => Http::response([
            'siteEntry' => [['siteUrl' => 'https://example.com/', 'permissionLevel' => 'siteOwner']],
        ], 200)]);

        Livewire::actingAs($user)->test(Websites::class)
            ->call('editMapping', $website->id)
            ->assertSet('editingWebsiteId', $website->id)
            ->set('editing_search_console_site_url', 'https://example.com/')
            ->call('saveMapping', $website->id)
            ->assertSet('editingWebsiteId', null);

        $website->refresh();
        $this->assertSame($account->id, $website->search_console_account_id);
        $this->assertSame('https://example.com/', $website->search_console_site_url);
    }

    public function test_websites_component_can_clear_an_existing_mapping(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create();
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);
        CurrentOrganization::clear();

        Http::fake(['www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => []], 200)]);

        Livewire::actingAs($user)->test(Websites::class)
            ->call('editMapping', $website->id)
            ->assertSet('editing_search_console_site_url', 'https://example.com/')
            ->set('editing_search_console_site_url', '')
            ->call('saveMapping', $website->id);

        $website->refresh();
        $this->assertNull($website->search_console_account_id);
        $this->assertNull($website->search_console_site_url);
    }

    // ---------------------------------------------------------------
    // Livewire: Analysis
    // ---------------------------------------------------------------

    public function test_analysis_component_shows_empty_state_before_any_analysis_has_run(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->assertSee('No analysis yet');
    }

    public function test_analysis_component_is_waiting_after_clicking_run_analysis(): void
    {
        // QUEUE_CONNECTION=sync in tests means a dispatched job runs
        // inline immediately - Queue::fake() here is load-bearing, not
        // decorative: without it this test would make a real PageSpeed/
        // Search Console network call via RunSiteAnalysisJob (that logic
        // already has its own dedicated tests above).
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        $component = Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->call('runAnalysis');

        $this->assertTrue($component->get('isWaiting'));

        Queue::assertPushed(RunSiteAnalysisJob::class, fn ($job) => $job->websiteId === $website->id);
    }

    public function test_analysis_component_stops_waiting_once_a_fresh_analysis_row_exists(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        $component = Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('analysisRequestedAt', now()->subSecond()->toISOString());

        $this->assertTrue($component->get('isWaiting'));

        SeoAnalysis::factory()->create([
            'seo_website_id' => $website->id,
            'analyzed_at' => now(),
        ]);

        // Forces a fresh render pass (what an actual wire:poll tick does)
        // rather than reading a value Livewire already memoized during the
        // set() call above - isWaiting is re-evaluated from the database
        // on every real render, not just once.
        $component->call('$refresh');

        $this->assertFalse($component->get('isWaiting'));
    }

    /**
     * Regression test for a real staging bug: before RunSiteAnalysisJob
     * had a failed() handler, a permanent job failure left isWaiting
     * stuck true forever, since nothing ever recorded a failure for this
     * to check against.
     */
    public function test_analysis_component_stops_waiting_and_shows_the_error_after_a_recorded_failure(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        $component = Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('analysisRequestedAt', now()->subSecond()->toISOString());

        $this->assertTrue($component->get('isWaiting'));

        $website->update([
            'last_analysis_failed_at' => now(),
            'last_analysis_error' => 'HTTP request returned status code 403',
        ]);

        $component->call('$refresh');

        $this->assertFalse($component->get('isWaiting'));
        $this->assertSame('HTTP request returned status code 403', $component->get('analysisError'));
        $component->assertSee('HTTP request returned status code 403');
    }

    public function test_analysis_component_ignores_a_failure_recorded_before_the_current_request(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create([
            'last_analysis_failed_at' => now()->subHour(),
            'last_analysis_error' => 'a stale failure from a previous click',
        ]);
        CurrentOrganization::clear();

        $component = Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('analysisRequestedAt', now()->toISOString());

        $this->assertTrue($component->get('isWaiting'));
        $this->assertNull($component->get('analysisError'));
    }

    public function test_analysis_component_exports_a_csv(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create(['url' => 'https://example.com']);
        SeoAnalysis::factory()->create([
            'seo_website_id' => $website->id,
            'title' => 'Example Page',
        ]);
        CurrentOrganization::clear();

        $response = Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->call('exportCsv');

        $response->assertStatus(200);
    }

    // ---------------------------------------------------------------
    // RobotsTxtAnalyzer
    // ---------------------------------------------------------------

    public function test_robots_txt_analyzer_parses_disallow_rules_and_flags_blocked_indexing(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response(<<<'TXT'
                User-agent: *
                Disallow: /

                Sitemap: https://example.com/sitemap_index.xml
                TXT, 200),
        ]);

        $result = app(RobotsTxtAnalyzer::class)->analyze('https://example.com');

        $this->assertTrue($result['exists']);
        $this->assertTrue($result['blocks_indexing']);
        $this->assertSame([['user_agent' => '*', 'path' => '/']], $result['disallow_rules']);
        $this->assertSame(['https://example.com/sitemap_index.xml'], $result['sitemap_directives']);
    }

    public function test_robots_txt_analyzer_does_not_flag_a_partial_disallow_as_blocking_indexing(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\n", 200),
        ]);

        $result = app(RobotsTxtAnalyzer::class)->analyze('https://example.com');

        $this->assertTrue($result['exists']);
        $this->assertFalse($result['blocks_indexing']);
        $this->assertSame([['user_agent' => '*', 'path' => '/admin']], $result['disallow_rules']);
    }

    public function test_robots_txt_analyzer_returns_a_clean_not_exists_result_for_a_missing_file(): void
    {
        Http::fake(['example.com/robots.txt' => Http::response('', 404)]);

        $result = app(RobotsTxtAnalyzer::class)->analyze('https://example.com');

        $this->assertFalse($result['exists']);
        $this->assertNull($result['parse_error']);
        $this->assertSame([], $result['disallow_rules']);
    }

    // ---------------------------------------------------------------
    // SitemapAnalyzer
    // ---------------------------------------------------------------

    public function test_sitemap_analyzer_counts_urls_in_a_plain_urlset(): void
    {
        Http::fake([
            'example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://example.com/</loc></url>
                    <url><loc>https://example.com/about</loc></url>
                </urlset>
                XML, 200),
        ]);

        $result = app(SitemapAnalyzer::class)->analyze('https://example.com/sitemap.xml');

        $this->assertTrue($result['exists']);
        $this->assertFalse($result['is_index']);
        $this->assertSame(2, $result['url_count']);
        $this->assertNull($result['parse_error']);
    }

    public function test_sitemap_analyzer_detects_a_sitemap_index_without_recursing_into_children(): void
    {
        Http::fake([
            'example.com/sitemap_index.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <sitemap><loc>https://example.com/sitemap-1.xml</loc></sitemap>
                    <sitemap><loc>https://example.com/sitemap-2.xml</loc></sitemap>
                    <sitemap><loc>https://example.com/sitemap-3.xml</loc></sitemap>
                </sitemapindex>
                XML, 200),
        ]);

        $result = app(SitemapAnalyzer::class)->analyze('https://example.com/sitemap_index.xml');

        $this->assertTrue($result['exists']);
        $this->assertTrue($result['is_index']);
        $this->assertSame(3, $result['child_sitemap_count']);
        $this->assertNull($result['url_count']);

        // Only the index itself was ever fetched - child sitemaps are
        // deliberately not recursively crawled (that would reopen the
        // "sitemaps can list thousands of URLs" problem one level up).
        Http::assertNotSent(fn ($request) => str($request->url())->contains('sitemap-1.xml'));
    }

    public function test_sitemap_analyzer_returns_a_clean_not_exists_result_for_a_missing_sitemap(): void
    {
        Http::fake(['example.com/sitemap.xml' => Http::response('', 404)]);

        $result = app(SitemapAnalyzer::class)->analyze('https://example.com/sitemap.xml');

        $this->assertFalse($result['exists']);
        $this->assertNull($result['parse_error']);
    }

    public function test_sitemap_analyzer_records_a_parse_error_for_malformed_xml(): void
    {
        Http::fake(['example.com/sitemap.xml' => Http::response('<urlset><url><loc>broken', 200)]);

        $result = app(SitemapAnalyzer::class)->analyze('https://example.com/sitemap.xml');

        $this->assertTrue($result['exists']);
        $this->assertNotNull($result['parse_error']);
    }

    // ---------------------------------------------------------------
    // AnalyzeRobotsAndSitemap
    // ---------------------------------------------------------------

    public function test_analyze_robots_and_sitemap_uses_the_sitemap_url_declared_in_robots_txt(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response("User-agent: *\nSitemap: https://example.com/custom-sitemap.xml\n", 200),
            'example.com/custom-sitemap.xml' => Http::response('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>', 200),
        ]);

        $result = app(AnalyzeRobotsAndSitemap::class)->execute('https://example.com');

        $this->assertSame('robots_txt', $result['sitemap_result']['source']);
        $this->assertSame('https://example.com/custom-sitemap.xml', $result['sitemap_result']['url']);
        $this->assertSame(1, $result['sitemap_result']['url_count']);
    }

    public function test_analyze_robots_and_sitemap_falls_back_to_the_default_sitemap_path(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response('', 404),
            'example.com/sitemap.xml' => Http::response('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>', 200),
        ]);

        $result = app(AnalyzeRobotsAndSitemap::class)->execute('https://example.com');

        $this->assertSame('default_fallback', $result['sitemap_result']['source']);
        $this->assertSame('https://example.com/sitemap.xml', $result['sitemap_result']['url']);
    }

    // ---------------------------------------------------------------
    // RunSiteAnalysis - robots.txt/sitemap integration
    // ---------------------------------------------------------------

    /**
     * Regression coverage for folding robots.txt/sitemap checks into the
     * existing "Run analysis now" flow (SEO Phase 9d).
     */
    public function test_run_site_analysis_persists_robots_txt_and_sitemap_results(): void
    {
        Http::fake([
            'example.com/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200),
            'example.com/sitemap.xml' => Http::response('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>', 200),
            'example.com/*' => Http::response('<html><head><title>Example</title></head></html>', 200),
            'pagespeedonline.googleapis.com/*' => Http::response([
                'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9]], 'audits' => []],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create(['url' => 'https://example.com']);

        $analysis = app(RunSiteAnalysis::class)->execute($website);

        $this->assertTrue($analysis->robots_txt_result['blocks_indexing']);
        $this->assertSame(1, $analysis->sitemap_result['url_count']);

        CurrentOrganization::clear();
    }

    // ---------------------------------------------------------------
    // SearchConsoleClient::queryPagesForKeyword
    // ---------------------------------------------------------------

    public function test_search_console_client_queries_pages_for_an_exact_keyword(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    ['keys' => ['https://example.com/wheelchairs'], 'clicks' => 12, 'impressions' => 100, 'ctr' => 0.12, 'position' => 4.5],
                ],
            ], 200),
        ]);

        $account = SearchConsoleAccount::factory()->make(['access_token' => 'test-token', 'token_expires_at' => now()->addHour()]);

        $rows = app(SearchConsoleClient::class)->queryPagesForKeyword($account, 'https://example.com/', 'wheelchair kuwait', '2026-07-01', '2026-07-28');

        $this->assertSame('https://example.com/wheelchairs', $rows[0]['keys'][0]);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['dimensions'] === ['page']
                && $data['dimensionFilterGroups'][0]['filters'][0]['dimension'] === 'query'
                && $data['dimensionFilterGroups'][0]['filters'][0]['operator'] === 'equals'
                && $data['dimensionFilterGroups'][0]['filters'][0]['expression'] === 'wheelchair kuwait';
        });
    }

    // ---------------------------------------------------------------
    // RunKeywordPageAnalysis
    // ---------------------------------------------------------------

    public function test_run_keyword_page_analysis_throws_when_website_is_not_mapped_to_search_console(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        app(RunKeywordPageAnalysis::class)->execute($website, ['wheelchair kuwait']);

        CurrentOrganization::clear();
    }

    public function test_run_keyword_page_analysis_throws_when_no_keywords_are_given(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(RunKeywordPageAnalysis::class)->execute($website, ['   ', '']);

        CurrentOrganization::clear();
    }

    public function test_run_keyword_page_analysis_creates_page_and_rank_rows_for_each_keyword(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    ['keys' => ['https://example.com/wheelchairs'], 'clicks' => 12, 'impressions' => 100, 'ctr' => 0.12, 'position' => 4.5],
                ],
            ], 200),
            'example.com/*' => Http::response('<html><head><title>Wheelchairs</title><meta name="description" content="Buy wheelchairs."></head></html>', 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        app(RunKeywordPageAnalysis::class)->execute($website, ['wheelchair kuwait']);

        $this->assertSame(1, SeoPageAnalysis::count());
        $page = SeoPageAnalysis::first();
        $this->assertSame('https://example.com/wheelchairs', $page->page_url);
        $this->assertSame('Wheelchairs', $page->title);
        $this->assertNotNull($page->crawled_at);

        $this->assertSame(1, SeoKeywordPageRank::count());
        $this->assertSame('wheelchair kuwait', SeoKeywordPageRank::first()->keyword);
        $this->assertSame(12, SeoKeywordPageRank::first()->clicks);

        $website->refresh();
        $this->assertSame(['wheelchair kuwait'], $website->last_keyword_check_keywords);
        $this->assertNotNull($website->last_keyword_analysis_completed_at);

        CurrentOrganization::clear();
    }

    /**
     * Real product requirement: a legitimately correct result can be zero
     * ranking pages (none of the submitted keywords rank for any page) -
     * this must still mark the check as completed, not leave it looking
     * like nothing ever ran.
     */
    public function test_run_keyword_page_analysis_marks_completed_even_with_zero_ranking_pages(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => []], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        app(RunKeywordPageAnalysis::class)->execute($website, ['a keyword nobody ranks for']);

        $this->assertSame(0, SeoPageAnalysis::count());
        $website->refresh();
        $this->assertNotNull($website->last_keyword_analysis_completed_at);

        CurrentOrganization::clear();
    }

    public function test_run_keyword_page_analysis_normalizes_and_caps_submitted_keywords(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => []], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        // 'Wheelchair Kuwait' and 'wheelchair kuwait' should dedupe to one
        // normalized entry, and the whole list should be capped at
        // MAX_KEYWORDS_PER_SUBMISSION (15) even though 17 are submitted.
        $submitted = array_merge(['Wheelchair Kuwait', 'wheelchair kuwait'], array_map(fn ($i) => "keyword $i", range(1, 15)));

        app(RunKeywordPageAnalysis::class)->execute($website, $submitted);

        $website->refresh();
        $this->assertCount(RunKeywordPageAnalysis::MAX_KEYWORDS_PER_SUBMISSION, $website->last_keyword_check_keywords);
        $this->assertSame('wheelchair kuwait', $website->last_keyword_check_keywords[0]);

        CurrentOrganization::clear();
    }

    public function test_run_keyword_page_analysis_only_crawls_the_capped_top_pages_by_aggregate_clicks(): void
    {
        $rows = collect(range(1, 30))->map(fn (int $i) => [
            'keys' => ["https://example.com/page-{$i}"],
            'clicks' => $i,
            'impressions' => 100,
            'ctr' => 0.1,
            'position' => 5.0,
        ])->all();

        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => $rows], 200),
            'example.com/*' => Http::response('<html><head><title>Crawled</title></head></html>', 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        app(RunKeywordPageAnalysis::class)->execute($website, ['wheelchair kuwait']);

        $this->assertSame(30, SeoPageAnalysis::count());
        $this->assertSame(RunKeywordPageAnalysis::MAX_UNIQUE_PAGES_CRAWLED, SeoPageAnalysis::whereNotNull('crawled_at')->count());

        // clicks === page number, so the top 25 by clicks are page-6..page-30.
        $crawledUrls = SeoPageAnalysis::whereNotNull('crawled_at')->pluck('page_url')->sort()->values()->all();
        $expectedUrls = collect(range(6, 30))->map(fn (int $i) => "https://example.com/page-{$i}")->sort()->values()->all();
        $this->assertSame($expectedUrls, $crawledUrls);

        // The uncrawled pages still have their rank rows, just no on-page summary.
        $uncrawled = SeoPageAnalysis::whereNull('crawled_at')->first();
        $this->assertNotNull($uncrawled);
        $this->assertNull($uncrawled->title);
        $this->assertGreaterThan(0, $uncrawled->keywordRanks()->count());

        CurrentOrganization::clear();
    }

    /**
     * One page's crawl failure must not discard the other pages' results
     * or any rank rows - deliberate divergence from RunSiteAnalysis's
     * homepage crawl, where a failure is allowed to fail the whole run.
     */
    public function test_run_keyword_page_analysis_records_a_crawl_error_without_discarding_other_pages(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    ['keys' => ['https://example.com/broken'], 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 5],
                    ['keys' => ['https://example.com/fine'], 'clicks' => 5, 'impressions' => 100, 'ctr' => 0.1, 'position' => 5],
                ],
            ], 200),
            'example.com/broken' => Http::response('', 500),
            'example.com/fine' => Http::response('<html><head><title>Fine</title></head></html>', 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        app(RunKeywordPageAnalysis::class)->execute($website, ['wheelchair kuwait']);

        $broken = SeoPageAnalysis::where('page_url', 'https://example.com/broken')->first();
        $this->assertNull($broken->crawled_at);
        $this->assertNotNull($broken->crawl_error);

        $fine = SeoPageAnalysis::where('page_url', 'https://example.com/fine')->first();
        $this->assertNotNull($fine->crawled_at);
        $this->assertSame('Fine', $fine->title);

        CurrentOrganization::clear();
    }

    /**
     * Re-running a keyword check for the same website must replace the
     * previous result set, not accumulate duplicates alongside it -
     * matches SeoKeywordMetric's existing replace-not-accumulate pattern.
     */
    public function test_run_keyword_page_analysis_replaces_prior_results_on_a_rerun(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [['keys' => ['https://example.com/page'], 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 3]],
            ], 200),
            'example.com/*' => Http::response('<html><head><title>Page</title></head></html>', 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);

        $action = app(RunKeywordPageAnalysis::class);
        $action->execute($website, ['first keyword']);
        $action->execute($website, ['second keyword']);

        $this->assertSame(1, SeoPageAnalysis::count());
        $this->assertSame(1, SeoKeywordPageRank::count());
        $this->assertSame('second keyword', SeoKeywordPageRank::first()->keyword);

        CurrentOrganization::clear();
    }

    // ---------------------------------------------------------------
    // RunKeywordPageAnalysisJob
    // ---------------------------------------------------------------

    public function test_run_keyword_page_analysis_job_runs_the_analysis(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [['keys' => ['https://example.com/page'], 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 3]],
            ], 200),
            'example.com/*' => Http::response('<html><head><title>Page</title></head></html>', 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create(['token_expires_at' => now()->addHour()]);
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);
        CurrentOrganization::clear();

        (new RunKeywordPageAnalysisJob($website->id, ['wheelchair kuwait']))->handle(app(RunKeywordPageAnalysis::class));

        $this->assertSame(1, SeoPageAnalysis::count());
    }

    public function test_run_keyword_page_analysis_job_is_a_no_op_for_a_deleted_website(): void
    {
        (new RunKeywordPageAnalysisJob(999999, ['wheelchair kuwait']))->handle(app(RunKeywordPageAnalysis::class));

        $this->assertSame(0, SeoPageAnalysis::count());
    }

    public function test_run_keyword_page_analysis_job_records_the_failure_on_the_website(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        (new RunKeywordPageAnalysisJob($website->id, ['wheelchair kuwait']))->failed(new \Exception('Search Console request failed'));

        $website->refresh();
        $this->assertNotNull($website->last_keyword_analysis_failed_at);
        $this->assertSame('Search Console request failed', $website->last_keyword_analysis_error);
    }

    // ---------------------------------------------------------------
    // Livewire: Analysis - keyword-driven page analysis
    // ---------------------------------------------------------------

    public function test_analysis_component_rejects_an_empty_keyword_submission(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create();
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);
        CurrentOrganization::clear();

        Queue::fake();

        Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('keywordsInput', '   ,  ,')
            ->call('runKeywordAnalysis')
            ->assertHasErrors('keywordsInput');

        Queue::assertNotPushed(RunKeywordPageAnalysisJob::class);
    }

    public function test_analysis_component_rejects_more_than_the_max_keywords(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create();
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);
        CurrentOrganization::clear();

        Queue::fake();

        $tooMany = implode(', ', array_map(fn ($i) => "keyword $i", range(1, RunKeywordPageAnalysis::MAX_KEYWORDS_PER_SUBMISSION + 1)));

        Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('keywordsInput', $tooMany)
            ->call('runKeywordAnalysis')
            ->assertHasErrors('keywordsInput');

        Queue::assertNotPushed(RunKeywordPageAnalysisJob::class);
    }

    public function test_analysis_component_requires_a_search_console_mapping_before_checking_keywords(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        Queue::fake();

        Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('keywordsInput', 'wheelchair kuwait')
            ->call('runKeywordAnalysis')
            ->assertHasErrors('keywordsInput');

        Queue::assertNotPushed(RunKeywordPageAnalysisJob::class);
    }

    public function test_analysis_component_dispatches_the_keyword_job_and_waits(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $account = SearchConsoleAccount::factory()->create();
        $website = SeoWebsite::factory()->create([
            'search_console_account_id' => $account->id,
            'search_console_site_url' => 'https://example.com/',
        ]);
        CurrentOrganization::clear();

        $component = Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('keywordsInput', 'wheelchair kuwait, wheelchair kuwait')
            ->call('runKeywordAnalysis');

        $this->assertTrue($component->get('isKeywordAnalysisWaiting'));

        Queue::assertPushed(RunKeywordPageAnalysisJob::class, fn ($job) => $job->websiteId === $website->id && $job->keywords === ['wheelchair kuwait']);
    }

    /**
     * The critical case getIsWaitingProperty()'s "does a fresh child row
     * exist" pattern cannot handle: a legitimately correct keyword-check
     * result can be zero rank rows, which must still stop the waiting
     * state via the explicit last_keyword_analysis_completed_at marker.
     */
    public function test_analysis_component_stops_waiting_for_keywords_even_with_zero_results(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        $component = Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('keywordAnalysisRequestedAt', now()->subSecond()->toISOString());

        $this->assertTrue($component->get('isKeywordAnalysisWaiting'));

        $website->update([
            'last_keyword_check_keywords' => ['a keyword nobody ranks for'],
            'last_keyword_analysis_completed_at' => now(),
        ]);

        $component->call('$refresh');

        $this->assertFalse($component->get('isKeywordAnalysisWaiting'));
    }

    public function test_analysis_component_stops_waiting_and_shows_the_keyword_error_after_a_recorded_failure(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create();
        CurrentOrganization::clear();

        $component = Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->set('keywordAnalysisRequestedAt', now()->subSecond()->toISOString());

        $website->update([
            'last_keyword_analysis_failed_at' => now(),
            'last_keyword_analysis_error' => 'Search Console request failed',
        ]);

        $component->call('$refresh');

        $this->assertFalse($component->get('isKeywordAnalysisWaiting'));
        $this->assertSame('Search Console request failed', $component->get('keywordAnalysisError'));
    }

    /**
     * The clicks/impressions/position figures are a rolling window (28
     * days), not a lifetime total - the UI must say which window, since
     * that wasn't visible anywhere before this test was added.
     */
    public function test_analysis_component_shows_the_keyword_check_data_period(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $website = SeoWebsite::factory()->create(['last_keyword_check_keywords' => ['wheelchair kuwait']]);
        SeoAnalysis::factory()->create(['seo_website_id' => $website->id]);
        $page = SeoPageAnalysis::factory()->create(['seo_website_id' => $website->id]);
        SeoKeywordPageRank::factory()->create([
            'seo_website_id' => $website->id,
            'seo_page_analysis_id' => $page->id,
            'period_start' => '2026-07-08',
            'period_end' => '2026-08-05',
        ]);
        CurrentOrganization::clear();

        Livewire::actingAs($user)->test(Analysis::class, ['website' => $website])
            ->assertSee('Jul 8')
            ->assertSee('Aug 5, 2026');
    }
}
