<?php

namespace Database\Factories;

use App\Domain\Seo\Models\SearchConsoleAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<SearchConsoleAccount>
 */
class SearchConsoleAccountFactory extends Factory
{
    /**
     * @var class-string<SearchConsoleAccount>
     */
    protected $model = SearchConsoleAccount::class;

    /**
     * Deliberately no default `organization_id` here - see PostFactory.
     */
    public function definition(): array
    {
        return [
            'google_email' => $this->faker->safeEmail(),
            'access_token' => $this->faker->uuid(),
            'refresh_token' => $this->faker->uuid(),
            'token_expires_at' => now()->addHour(),
            'disabled_at' => null,
        ];
    }
}
