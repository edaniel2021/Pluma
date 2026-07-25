<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;

class CreatePersonalOrganization
{
    /**
     * Create and attach a personal organization for a newly created user.
     *
     * Shared by Fortify's registration action and the Socialite sign-up
     * flow, since both need to give a brand-new user a personal org.
     */
    public function create(User $user): Organization
    {
        $organization = Organization::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Organization",
            'personal_team' => true,
        ]);

        $user->ownedTeams()->save($organization);

        return $organization;
    }
}
