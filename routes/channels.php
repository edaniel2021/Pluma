<?php

use App\Domain\Agents\Models\AgentThread;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Authorizes App\Livewire\Agents\Chat's real-time listener - bypasses
 * AgentThread's BelongsToOrganization scope explicitly (rather than relying
 * on it resolving $user's current org implicitly) since a channel-auth
 * closure isn't really "a request for the current organization" in the same
 * sense a normal page load is.
 */
Broadcast::channel('agent-thread.{threadId}', function ($user, $threadId) {
    $thread = AgentThread::withoutGlobalScope('organization')->find($threadId);

    return $thread && $thread->organization_id === $user->currentTeam?->id;
});
