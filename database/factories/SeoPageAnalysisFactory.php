<?php

namespace Database\Factories;

use App\Domain\Seo\Models\SeoPageAnalysis;
use App\Domain\Seo\Models\SeoWebsite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<SeoPageAnalysis>
 */
class SeoPageAnalysisFactory extends Factory
{
    /**
     * @var class-string<SeoPageAnalysis>
     */
    protected $model = SeoPageAnalysis::class;

    public function definition(): array
    {
        return [
            'seo_website_id' => SeoWebsite::factory(),
            'page_url' => $this->faker->url(),
            'title' => $this->faker->sentence(4),
            'meta_description' => $this->faker->sentence(12),
            'h1s' => [$this->faker->sentence(3)],
            'h2s' => [$this->faker->sentence(3), $this->faker->sentence(3)],
            'crawled_at' => now(),
            'crawl_error' => null,
        ];
    }
}
