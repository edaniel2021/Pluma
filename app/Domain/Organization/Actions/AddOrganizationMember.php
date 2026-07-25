<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class AddOrganizationMember implements AddsTeamMembers
{
    /**
     * Add a new member to the given organization.
     */
    public function add(User $user, Organization $organization, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $organization);

        $this->validate($organization, $email, $role);

        $newMember = Jetstream::findUserByEmailOrFail($email);

        AddingTeamMember::dispatch($organization, $newMember);

        $organization->users()->attach(
            $newMember, ['role' => $role]
        );

        TeamMemberAdded::dispatch($organization, $newMember);
    }

    /**
     * Validate the add member operation.
     */
    protected function validate(Organization $organization, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules(), [
            'email.exists' => __('We were unable to find a registered user with this email address.'),
        ])->after(
            $this->ensureUserIsNotAlreadyOnOrganization($organization, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for adding a member.
     *
     * @return array<string, Rule|array|string>
     */
    protected function rules(): array
    {
        return array_filter([
            'email' => ['required', 'email', 'exists:users'],
            'role' => Jetstream::hasRoles()
                            ? ['required', 'string', new Role]
                            : null,
        ]);
    }

    /**
     * Ensure that the user is not already on the organization.
     */
    protected function ensureUserIsNotAlreadyOnOrganization(Organization $organization, string $email): Closure
    {
        return function ($validator) use ($organization, $email) {
            $validator->errors()->addIf(
                $organization->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the organization.')
            );
        };
    }
}
