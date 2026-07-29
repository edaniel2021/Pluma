<?php

namespace Database\Factories;

use App\Domain\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<WhatsAppAccount>
 */
class WhatsAppAccountFactory extends Factory
{
    /**
     * @var class-string<WhatsAppAccount>
     */
    protected $model = WhatsAppAccount::class;

    /**
     * Deliberately no default `organization_id` here - see PostFactory.
     */
    public function definition(): array
    {
        return [
            'waba_id' => $this->faker->numerify('##########'),
            'phone_number_id' => $this->faker->numerify('##########'),
            'display_phone_number' => $this->faker->e164PhoneNumber(),
            'access_token' => $this->faker->uuid(),
            'connected_at' => now(),
        ];
    }
}
