<?php

namespace App\Domain\Agents\Jobs;

use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Models\AgentThread;
use App\Domain\Agents\Support\AgentConversationService;
use App\Domain\Organization\Support\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Takes just a thread ID (not the model), same reasoning as PublishPostJob:
 * a fresh copy is fetched at execution time so an in-flight reply survives
 * deploys/queue restarts. This is what makes the chat UI's wire:poll
 * "polling for v1" approach work - the user's message is saved and this
 * job dispatched synchronously, then the Livewire component just polls
 * until the assistant's reply row shows up.
 */
class ProcessAgentMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * Overrides Horizon's supervisor-level default (60s, config/horizon.php)
     * - a job's own $timeout takes precedence over that. Needs to stay
     * comfortably above config('openai.request_timeout') (90s): a turn that
     * calls generate_image can spend most of its time in that one HTTP call,
     * plus a couple of fast chat-completion round trips either side of it.
     */
    public int $timeout = 150;

    public function __construct(public int $threadId)
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
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // expireAfter must exceed $timeout above, or the lock could expire
        // and let a duplicate job start while a legitimately slow (but still
        // running) attempt is still mid-flight.
        return [(new WithoutOverlapping((string) $this->threadId))->releaseAfter(30)->expireAfter(200)];
    }

    public function handle(AgentConversationService $conversation): void
    {
        $thread = AgentThread::withoutGlobalScope('organization')->find($this->threadId);

        if (! $thread) {
            return;
        }

        CurrentOrganization::set($thread->organization);

        try {
            $conversation->respond($thread);
        } finally {
            CurrentOrganization::clear();
        }
    }

    /**
     * Called once all retry attempts are exhausted - surfaces a visible
     * reply so the user isn't left staring at a spinner forever after a
     * permanent failure (bad/missing API key, etc).
     */
    public function failed(?Throwable $exception): void
    {
        $thread = AgentThread::withoutGlobalScope('organization')->find($this->threadId);

        if (! $thread) {
            return;
        }

        CurrentOrganization::set($thread->organization);

        $thread->messages()->create([
            'role' => AgentMessageRole::Assistant,
            'content' => 'Sorry, something went wrong generating a reply: '.($exception?->getMessage() ?? 'unknown error'),
        ]);

        CurrentOrganization::clear();
    }
}
