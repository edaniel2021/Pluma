<?php

namespace Database\Factories;

use App\Domain\Posts\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @var class-string<Tag>
     */
    protected $model = Tag::class;

    /**
     * Deliberately no default `organization_id` here - see PostFactory.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->hexColor(),
        ];
    }
}
