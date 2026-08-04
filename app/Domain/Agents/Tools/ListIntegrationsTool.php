<?php

namespace App\Domain\Agents\Tools;

use App\Domain\Agents\Contracts\AgentToolContract;
use App\Domain\Agents\Models\AgentThread;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

class ListIntegrationsTool implements AgentToolContract
{
    public function name(): string
    {
        return 'list_channels';
    }

    public function description(): string
    {
        return "Lists the organization's connected social media channels that posts can be scheduled to. Always call this before scheduling a post if you don't already know the channel id.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass,
            'required' => [],
        ];
    }

    public function handle(array $arguments, Organization $organization, User $user, AgentThread $thread): array
    {
        return [
            'channels' => $organization->integrations
                ->map(fn (Integration $integration) => [
                    'id' => $integration->id,
                    'platform' => $integration->provider,
                    'name' => $integration->account_name,
                    'disabled' => $integration->isDisabled(),
                ])
                ->values()
                ->all(),
        ];
    }
}
