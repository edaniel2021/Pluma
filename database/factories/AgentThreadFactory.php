<?php

namespace Database\Factories;

use App\Domain\Agents\Models\AgentThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<AgentThread>
 */
class AgentThreadFactory extends Factory
{
    /**
     * @var class-string<AgentThread>
     */
    protected $model = AgentThread::class;

    /**
     * Deliberately no default `organization_id` here: BelongsToOrganization
     * auto-fills it from CurrentOrganization at creation time, same as
     * PostFactory.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
        ];
    }
}
