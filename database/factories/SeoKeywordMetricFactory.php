<?php

namespace Database\Factories;

use App\Domain\Seo\Models\SeoKeywordMetric;
use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<SeoKeywordMetric>
 */
class SeoKeywordMetricFactory extends Factory
{
    /**
     * @var class-string<SeoKeywordMetric>
     */
    protected $model = SeoKeywordMetric::class;

    public function definition(): array
    {
        $impressions = $this->faker->numberBetween(10, 1000);

        return [
            'seo_website_id' => SeoWebsite::factory(),
            'query' => $this->faker->words(3, true),
            'clicks' => $this->faker->numberBetween(0, $impressions),
            'impressions' => $impressions,
            'ctr' => $this->faker->randomFloat(4, 0, 1),
            'position' => $this->faker->randomFloat(2, 1, 50),
            'period_start' => now()->subDays(28)->toDateString(),
            'period_end' => now()->toDateString(),
            'pulled_at' => now(),
        ];
    }
}
