<?php

namespace App\Livewire\Seo;

use App\Domain\Seo\Models\SearchConsoleAccount;
use App\Domain\Seo\Models\SeoWebsite;
use App\Domain\Seo\Support\SearchConsoleClient;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

/**
 * The main SEO config page: connect Google Search Console, then add/remove
 * tracked websites and optionally map each one to a verified GSC property.
 */
class Websites extends Component
{
    public string $url = '';

    public string $search_console_site_url = '';

    /**
     * Which existing website's mapping row is currently open for editing -
     * null means none. Separate from the "add website" form above: a
     * website can only be mapped to a GSC property at creation time
     * otherwise, with no way back in short of deleting and recreating it
     * (losing its whole analysis history in the process, since
     * SeoAnalysis/SeoKeywordMetric cascade-delete with their website).
     */
    public ?int $editingWebsiteId = null;

    public string $editing_search_console_site_url = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'url' => ['required', 'url'],
            'search_console_site_url' => ['nullable', 'string'],
        ];
    }

    public function addWebsite(): void
    {
        $validated = $this->validate();
        $account = Auth::user()->currentTeam->searchConsoleAccounts->first();
        $mapped = $validated['search_console_site_url'] !== '' && $account;

        SeoWebsite::create([
            'url' => $validated['url'],
            'search_console_account_id' => $mapped ? $account->id : null,
            'search_console_site_url' => $mapped ? $validated['search_console_site_url'] : null,
        ]);

        $this->reset(['url', 'search_console_site_url']);
    }

    public function removeWebsite(SeoWebsite $website): void
    {
        $website->delete();
    }

    public function editMapping(SeoWebsite $website): void
    {
        $this->editingWebsiteId = $website->id;
        $this->editing_search_console_site_url = $website->search_console_site_url ?? '';
    }

    public function cancelEditingMapping(): void
    {
        $this->reset(['editingWebsiteId', 'editing_search_console_site_url']);
    }

    public function saveMapping(SeoWebsite $website): void
    {
        $this->validate(['editing_search_console_site_url' => ['nullable', 'string']]);

        $account = Auth::user()->currentTeam->searchConsoleAccounts->first();
        $mapped = $this->editing_search_console_site_url !== '' && $account;

        $website->update([
            'search_console_account_id' => $mapped ? $account->id : null,
            'search_console_site_url' => $mapped ? $this->editing_search_console_site_url : null,
        ]);

        $this->reset(['editingWebsiteId', 'editing_search_console_site_url']);
    }

    public function disconnectSearchConsole(SearchConsoleAccount $account): void
    {
        $account->delete();
    }

    public function render(SearchConsoleClient $searchConsole)
    {
        $organization = Auth::user()->currentTeam;
        $account = $organization->searchConsoleAccounts->first();
        $availableSites = [];

        // A live call on every render is acceptable here (sites.list is a
        // cheap, fast lookup, not something like image generation) - but a
        // network hiccup or a disabled/expired account must not break the
        // whole config page, just leave the property dropdown empty.
        if ($account && ! $account->isDisabled()) {
            try {
                $availableSites = $searchConsole->listSites($account);
            } catch (Throwable) {
                $availableSites = [];
            }
        }

        return view('livewire.seo.websites', [
            'websites' => $organization->seoWebsites,
            'searchConsoleAccount' => $account,
            'availableSites' => $availableSites,
        ]);
    }
}
