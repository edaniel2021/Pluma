<?php

namespace App\Http\Controllers;

use App\Domain\Seo\Actions\ConnectSearchConsole;
use App\Domain\Seo\Support\SearchConsoleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

/**
 * OAuth connect/callback for Google Search Console - a bespoke pair, not
 * IntegrationConnectController's registry-driven flow, since Search
 * Console isn't a SocialProviderContract (see the migration for why).
 * Mirrors IntegrationConnectController's shape otherwise.
 */
class SearchConsoleConnectController extends Controller
{
    public function __construct(
        private readonly ConnectSearchConsole $connectSearchConsole,
        private readonly SearchConsoleClient $searchConsole,
    ) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('search-console')
            ->scopes(['https://www.googleapis.com/auth/webmasters.readonly'])
            // Forces a refresh_token to actually be issued - same as
            // YouTubeProvider's redirectParameters().
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $socialiteUser = Socialite::driver('search-console')->user();

        $account = $this->connectSearchConsole->execute($socialiteUser);

        // A live sites.list call right after connecting both confirms the
        // token/scope actually works and gives an accurate confirmation
        // message, rather than a blind "Connected." - the Websites page
        // re-fetches this list live itself when it needs it, so nothing
        // is cached here.
        $siteCount = count($this->searchConsole->listSites($account));

        $message = $siteCount === 1
            ? 'Connected Google Search Console - 1 verified property found.'
            : "Connected Google Search Console - {$siteCount} verified properties found.";

        return redirect()->route('seo.index')->banner($message);
    }
}
