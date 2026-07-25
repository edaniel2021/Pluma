<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Events\TeamMemberRemoved;

class RemoveOrganizationMember implements RemovesTeamMembers
{
    /**
     * Remove the organization member from the given organization.
     */
    public function remove(User $user, Organization $organization, User $organizationMember): void
    {
        $this->authorize($user, $organization, $organizationMember);

        $this->ensureUserDoesNotOwnOrganization($organizationMember, $organization);

        $organization->removeUser($organizationMember);

        TeamMemberRemoved::dispatch($organization, $organizationMember);
    }

    /**
     * Authorize that the user can remove the organization member.
     */
    protected function authorize(User $user, Organization $organization, User $organizationMember): void
    {
        if (! Gate::forUser($user)->check('removeTeamMember', $organization) &&
            $user->id !== $organizationMember->id) {
            throw new AuthorizationException;
        }
    }

    /**
     * Ensure that the currently authenticated user does not own the organization.
     */
    protected function ensureUserDoesNotOwnOrganization(User $organizationMember, Organization $organization): void
    {
        if ($organizationMember->id === $organization->owner->id) {
            throw ValidationException::withMessages([
                'team' => [__('You may not leave an organization that you created.')],
            ])->errorBag('removeTeamMember');
        }
    }
}
