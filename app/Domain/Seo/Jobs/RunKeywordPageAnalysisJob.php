<?php

namespace App\Domain\Seo\Jobs;

use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Seo\Actions\RunKeywordPageAnalysis;
use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dispatched when the user submits a keyword list on the Analysis page.
 * Runs on its own `redis-seo-keywords` queue connection/supervisor (see
 * config/queue.php, config/horizon.php) rather than the main `redis`
 * connection - its worst-case sequential cost (up to
 * RunKeywordPageAnalysis::MAX_KEYWORDS_PER_SUBMISSION GSC queries plus
 * MAX_UNIQUE_PAGES_CRAWLED page crawls, each up to 30s) is large enough
 * that sharing the main connection's retry_after=260 would mean bumping
 * that shared value 5x for every job on the connection just to
 * accommodate this one rare, opt-in, heavy feature.
 */
class RunKeywordPageAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Deliberately 1, not 3 like RunSiteAnalysisJob - with a worst case
     * already at ~20 minutes, retrying on failure would push the
     * theoretical ceiling past an hour for what's a user-initiated,
     * easily-resubmitted operation.
     */
    public int $tries = 1;

    /**
     * Worst case: 15 GSC page-dimension queries x
     * SearchConsoleClient::REQUEST_TIMEOUT_SECONDS (30s) + 25 page crawls
     * x PageCrawler::REQUEST_TIMEOUT_SECONDS (30s) = 1200s, plus a buffer
     * for DB writes/dedup overhead.
     */
    public int $timeout = 1320;

    /**
     * @param  array<int, string>  $keywords
     */
    public function __construct(public int $websiteId, public array $keywords)
    {
        // Routed to its own connection/supervisor here on the job class
        // itself, not left to the dispatch call site - see class
        // docblock. Set in the constructor, not as a typed property
        // declaration: Queueable already declares an untyped $connection
        // property, and a conflicting typed re-declaration with a default
        // value is a fatal trait-composition error at class-load time.
        $this->connection = 'redis-seo-keywords';
    }

    /**
     * expireAfter must exceed $timeout above, or the lock could expire and
     * let a duplicate job start while a legitimately slow (but still
     * running) attempt is still mid-flight.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->websiteId))->releaseAfter(30)->expireAfter(1380)];
    }

    public function handle(RunKeywordPageAnalysis $runKeywordPageAnalysis): void
    {
        $website = SeoWebsite::withoutGlobalScope('organization')->find($this->websiteId);

        if (! $website) {
            return;
        }

        CurrentOrganization::set($website->organization);

        try {
            $runKeywordPageAnalysis->execute($website, $this->keywords);
        } finally {
            CurrentOrganization::clear();
        }
    }

    /**
     * Called on the single permanent failure (tries=1, no retries).
     * Without this, the "Check keyword rankings" UI would be stuck
     * waiting forever on failure - same reasoning as
     * RunSiteAnalysisJob::failed().
     */
    public function failed(?Throwable $exception): void
    {
        $website = SeoWebsite::withoutGlobalScope('organization')->find($this->websiteId);

        if (! $website) {
            return;
        }

        CurrentOrganization::set($website->organization);

        $website->update([
            'last_keyword_analysis_failed_at' => now(),
            'last_keyword_analysis_error' => $exception?->getMessage() ?? 'Unknown error',
        ]);

        CurrentOrganization::clear();
    }
}
