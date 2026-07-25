<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use Laravel\Jetstream\Contracts\DeletesTeams;

class DeleteOrganization implements DeletesTeams
{
    /**
     * Delete the given organization.
     */
    public function delete(Organization $organization): void
    {
        $organization->purge();
    }
}
