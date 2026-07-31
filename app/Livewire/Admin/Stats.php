<?php

namespace App\Livewire\Admin;

use App\Domain\Agents\Models\AgentThread;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organization\Models\Organization;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Models\PostError;
use App\Models\User;
use Livewire\Component;

/**
 * Platform-wide counts for the SaaS operator - every BelongsToOrganization
 * query below bypasses the tenancy scope on purpose (Organization/User have
 * no such scope to begin with, since they're the tenant/its members).
 */
class Stats extends Component
{
    public function render()
    {
        $postsByState = Post::withoutGlobalScope('organization')
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state');

        return view('livewire.admin.stats', [
            'organizationCount' => Organization::count(),
            'userCount' => User::count(),
            'integrationCount' => Integration::withoutGlobalScope('organization')->count(),
            'agentThreadCount' => AgentThread::withoutGlobalScope('organization')->count(),
            'postsByState' => collect(PostState::cases())
                ->mapWithKeys(fn (PostState $state) => [$state->label() => $postsByState->get($state->value, 0)]),
            'errorsLast24Hours' => PostError::where('created_at', '>=', now()->subDay())->count(),
            'errorsLast7Days' => PostError::where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }
}
