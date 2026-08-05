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
use Throwable;

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

    /**
     * Overrides Horizon's supervisor-level default (60s, config/horizon.php)
     * - a job's own $timeout takes precedence over that. Needs to stay
     * comfortably above the worst-case sequential cost of one attempt:
     * a crawl plus two PageSpeedClient calls (each up to
     * PageSpeedClient::REQUEST_TIMEOUT_SECONDS = 60s) plus an optional
     * Search Console query. Same "openai.request_timeout < job $timeout"
     * nesting requirement as the AI Assistant's gpt-image-1 gotcha.
     */
    public int $timeout = 200;

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
     * expireAfter must exceed $timeout above, or the lock could expire
     * and let a duplicate job start while a legitimately slow (but still
     * running) attempt is still mid-flight.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->websiteId))->releaseAfter(30)->expireAfter(260)];
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

            // A retry that eventually succeeds must clear any earlier
            // failure - otherwise a stale last_analysis_failed_at from a
            // prior attempt could still be newer than a later
            // analysisRequestedAt and incorrectly report failure again.
            if ($website->last_analysis_failed_at) {
                $website->update(['last_analysis_failed_at' => null, 'last_analysis_error' => null]);
            }
        } finally {
            CurrentOrganization::clear();
        }
    }

    /**
     * Called once all retry attempts are exhausted. Without this, a
     * permanent failure left the "Run analysis now" button stuck on
     * "Analyzing..." forever - Analysis::getIsWaitingProperty() only knew
     * how to detect a *successful* fresh SeoAnalysis row, never a
     * failure, so nothing ever told it to stop waiting.
     */
    public function failed(?Throwable $exception): void
    {
        $website = SeoWebsite::withoutGlobalScope('organization')->find($this->websiteId);

        if (! $website) {
            return;
        }

        CurrentOrganization::set($website->organization);

        $website->update([
            'last_analysis_failed_at' => now(),
            'last_analysis_error' => $exception?->getMessage() ?? 'Unknown error',
        ]);

        CurrentOrganization::clear();
    }
}
