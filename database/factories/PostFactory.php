<?php

namespace Database\Factories;

use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @var class-string<Post>
     */
    protected $model = Post::class;

    /**
     * Deliberately no default `organization_id` here: BelongsToOrganization
     * auto-fills it from CurrentOrganization at creation time. Tests that
     * need a specific org should use `Post::factory()->for($org, 'organization')`
     * or `CurrentOrganization::set($org)` before calling create().
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'content' => $this->faker->realText(200),
            'state' => PostState::Draft,
            'scheduled_at' => null,
            'published_at' => null,
        ];
    }
}
