<?php

namespace Database\Factories;

use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<SeoWebsite>
 */
class SeoWebsiteFactory extends Factory
{
    /**
     * @var class-string<SeoWebsite>
     */
    protected $model = SeoWebsite::class;

    /**
     * Deliberately no default `organization_id` here - see PostFactory.
     */
    public function definition(): array
    {
        return [
            'url' => $this->faker->unique()->url(),
            'search_console_account_id' => null,
            'search_console_site_url' => null,
        ];
    }
}
