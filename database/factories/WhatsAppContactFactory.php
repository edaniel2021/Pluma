<?php

namespace Database\Factories;

use App\Domain\WhatsApp\Models\WhatsAppAccount;
use App\Domain\WhatsApp\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<WhatsAppContact>
 */
class WhatsAppContactFactory extends Factory
{
    /**
     * @var class-string<WhatsAppContact>
     */
    protected $model = WhatsAppContact::class;

    public function definition(): array
    {
        return [
            'whatsapp_account_id' => WhatsAppAccount::factory(),
            'phone_number' => $this->faker->unique()->e164PhoneNumber(),
            'name' => $this->faker->name(),
            'opted_in_at' => now(),
        ];
    }
}
