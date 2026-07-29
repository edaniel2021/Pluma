<?php

namespace App\Domain\WhatsApp\Actions;

use App\Domain\WhatsApp\Models\WhatsAppBroadcast;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends a pre-approved Meta template message to every pending recipient
 * already attached to the broadcast (the caller creates those rows first,
 * one per selected contact, before dispatching the send). Per-recipient
 * failures don't stop the rest of the run - each is recorded individually.
 */
class SendWhatsAppBroadcast
{
    public function execute(WhatsAppBroadcast $broadcast): void
    {
        $account = $broadcast->account;

        $broadcast->update(['status' => 'sending']);

        $allSucceeded = true;

        foreach ($broadcast->recipients()->where('status', 'pending')->with('contact')->get() as $recipient) {
            try {
                $response = Http::withToken($account->access_token)
                    ->post("https://graph.facebook.com/v23.0/{$account->phone_number_id}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $recipient->contact->phone_number,
                        'type' => 'template',
                        'template' => [
                            'name' => $broadcast->template_name,
                            'language' => ['code' => $broadcast->template_language],
                        ],
                    ]);

                $messageId = $response->json('messages.0.id');

                if ($response->successful() && $messageId) {
                    $recipient->update([
                        'status' => 'sent',
                        'message_id' => $messageId,
                        'sent_at' => now(),
                    ]);
                } else {
                    $allSucceeded = false;
                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => $response->body(),
                    ]);
                }
            } catch (Throwable $e) {
                $allSucceeded = false;
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        $broadcast->update([
            'status' => $allSucceeded ? 'sent' : 'failed',
            'sent_at' => now(),
        ]);
    }
}
