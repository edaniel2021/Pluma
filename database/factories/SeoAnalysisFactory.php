<?php

namespace Database\Factories;

use App\Domain\Seo\Models\SeoAnalysis;
use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<SeoAnalysis>
 */
class SeoAnalysisFactory extends Factory
{
    /**
     * @var class-string<SeoAnalysis>
     */
    protected $model = SeoAnalysis::class;

    public function definition(): array
    {
        return [
            'seo_website_id' => SeoWebsite::factory(),
            'analyzed_at' => now(),
            'title' => $this->faker->sentence(4),
            'meta_description' => $this->faker->sentence(12),
            'h1s' => [$this->faker->sentence(3)],
            'h2s' => [$this->faker->sentence(3), $this->faker->sentence(3)],
            'desktop_response_ms' => $this->faker->numberBetween(200, 900),
            'mobile_response_ms' => $this->faker->numberBetween(300, 1200),
            'desktop_score' => $this->faker->numberBetween(60, 100),
            'mobile_score' => $this->faker->numberBetween(40, 100),
        ];
    }
}
