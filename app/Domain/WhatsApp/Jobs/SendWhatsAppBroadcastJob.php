<?php

namespace App\Domain\WhatsApp\Jobs;

use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\WhatsApp\Actions\SendWhatsAppBroadcast;
use App\Domain\WhatsApp\Models\WhatsAppBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued (not sent synchronously from the request) since a broadcast can
 * fan out to many recipients over the network - the Livewire component
 * creates the broadcast + pending recipient rows first, then dispatches
 * this to actually call Meta's API for each one.
 */
class SendWhatsAppBroadcastJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $broadcastId)
    {
    }

    public function handle(SendWhatsAppBroadcast $action): void
    {
        $broadcast = WhatsAppBroadcast::with('account')->find($this->broadcastId);

        if (! $broadcast) {
            return;
        }

        CurrentOrganization::set($broadcast->account->organization);

        try {
            $action->execute($broadcast);
        } finally {
            CurrentOrganization::clear();
        }
    }
}
