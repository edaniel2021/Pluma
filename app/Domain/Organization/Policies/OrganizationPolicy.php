<?php

namespace App\Domain\Organization\Policies;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToTeam($organization);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $user->ownsTeam($organization);
    }

    /**
     * Determine whether the user can add organization members.
     */
    public function addTeamMember(User $user, Organization $organization): bool
    {
        return $user->ownsTeam($organization);
    }

    /**
     * Determine whether the user can update organization member permissions.
     */
    public function updateTeamMember(User $user, Organization $organization): bool
    {
        return $user->ownsTeam($organization);
    }

    /**
     * Determine whether the user can remove organization members.
     */
    public function removeTeamMember(User $user, Organization $organization): bool
    {
        return $user->ownsTeam($organization);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organization $organization): bool
    {
        return $user->ownsTeam($organization);
    }
}
