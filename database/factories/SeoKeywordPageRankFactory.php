<?php

namespace Database\Factories;

use App\Domain\Seo\Models\SeoKeywordPageRank;
use App\Domain\Seo\Models\SeoPageAnalysis;
use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<SeoKeywordPageRank>
 */
class SeoKeywordPageRankFactory extends Factory
{
    /**
     * @var class-string<SeoKeywordPageRank>
     */
    protected $model = SeoKeywordPageRank::class;

    public function definition(): array
    {
        $impressions = $this->faker->numberBetween(10, 1000);

        return [
            'seo_website_id' => SeoWebsite::factory(),
            'seo_page_analysis_id' => SeoPageAnalysis::factory(),
            'keyword' => $this->faker->words(3, true),
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
