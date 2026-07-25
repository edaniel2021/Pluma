<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Create a new action instance.
     */
    public function __construct(protected DeletesTeams $deletesTeams)
    {
    }

    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->deleteOrganizations($user);
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
    }

    /**
     * Delete the organizations and memberships attached to the user.
     */
    protected function deleteOrganizations(User $user): void
    {
        $user->teams()->detach();

        $user->ownedTeams->each(function (Organization $organization) {
            $this->deletesTeams->delete($organization);
        });
    }
}
