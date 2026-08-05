<?php

namespace Tests\Feature;

use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Seo\Actions\ComputeTrendingKeywords;
use App\Domain\Seo\Actions\ConnectSearchConsole;
use App\Domain\Seo\Actions\RunSiteAnalysis;
use App\Domain\Seo\Jobs\RunSiteAnalysisJob;
use App\Domain\Seo\Models\SearchConsoleAccount;
use App\Domain\Seo\Models\SeoAnalysis;
use App\Domain\Seo\Models\SeoKeywordMetric;
use App\Domain\Seo\Models\SeoWebsite;
use App\Domain\Seo\Support\PageCrawler;
use App\Domain\Seo\Support\PageSpeedClient;
use App\Domain\Seo\Support\SearchConsoleClient;
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
}
