<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\UpdatesTeamNames;

class UpdateOrganizationName implements UpdatesTeamNames
{
    /**
     * Validate and update the given organization's name and timezone.
     *
     * Also handles `timezone` despite the class/contract name being about
     * names only (a Jetstream contract we don't own) - it's the one form
     * already wired to the Organization Settings page's basic-info section,
     * and a scheduling timezone is exactly that: basic org info, not
     * worth a second form/action/Livewire component of its own.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, Organization $organization, array $input): void
    {
        Gate::forUser($user)->authorize('update', $organization);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
        ])->validateWithBag('updateTeamName');

        $organization->forceFill([
            'name' => $input['name'],
            'timezone' => $input['timezone'],
        ])->save();
    }
}
