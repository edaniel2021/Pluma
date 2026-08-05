<?php

namespace App\Domain\Seo\Jobs;

use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Seo\Actions\RunSiteAnalysis;
use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when the user clicks "Run analysis now" - a crawl + two
 * PageSpeed calls + (if mapped) a Search Console query together
 * realistically take several seconds to tens of seconds, the same "don't
 * block a web request on a slow external call" reasoning as
 * PublishPostJob/ProcessAgentMessageJob. Takes just the website ID (not
 * the model), same deploy-safety reasoning as those jobs.
 */
class RunSiteAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $websiteId)
    {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Guards against a double-click (or a future scheduled audit) firing
     * a second analysis for the same website while one is still running.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->websiteId))->releaseAfter(30)->expireAfter(120)];
    }

    public function handle(RunSiteAnalysis $runSiteAnalysis): void
    {
        $website = SeoWebsite::withoutGlobalScope('organization')->find($this->websiteId);

        if (! $website) {
            return;
        }

        CurrentOrganization::set($website->organization);

        try {
            $runSiteAnalysis->execute($website);
        } finally {
            CurrentOrganization::clear();
        }
    }
}
