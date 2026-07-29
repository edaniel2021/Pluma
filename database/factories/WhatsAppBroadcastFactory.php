<?php

namespace Database\Factories;

use App\Domain\WhatsApp\Models\WhatsAppAccount;
use App\Domain\WhatsApp\Models\WhatsAppBroadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<WhatsAppBroadcast>
 */
class WhatsAppBroadcastFactory extends Factory
{
    /**
     * @var class-string<WhatsAppBroadcast>
     */
    protected $model = WhatsAppBroadcast::class;

    public function definition(): array
    {
        return [
            'whatsapp_account_id' => WhatsAppAccount::factory(),
            'template_name' => 'hello_world',
            'template_language' => 'en_US',
            'status' => 'draft',
            'sent_at' => null,
        ];
    }
}
