<?php

namespace Database\Factories;

use App\Domain\Integrations\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    /**
     * @var class-string<Integration>
     */
    protected $model = Integration::class;

    /**
     * Deliberately no default `organization_id` here - see PostFactory.
     */
    public function definition(): array
    {
        return [
            'provider' => 'fake',
            'account_id' => $this->faker->unique()->numerify('##########'),
            'account_name' => $this->faker->userName(),
            'avatar_url' => null,
            'access_token' => $this->faker->uuid(),
            'refresh_token' => $this->faker->uuid(),
            'token_expires_at' => now()->addDays(60),
            'disabled_at' => null,
        ];
    }
}
