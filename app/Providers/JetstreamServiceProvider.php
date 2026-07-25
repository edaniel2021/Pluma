<?php

namespace App\Providers;

use App\Domain\Auth\Actions\DeleteUser;
use App\Domain\Organization\Actions\AddOrganizationMember;
use App\Domain\Organization\Actions\CreateOrganization;
use App\Domain\Organization\Actions\DeleteOrganization;
use App\Domain\Organization\Actions\InviteOrganizationMember;
use App\Domain\Organization\Actions\RemoveOrganizationMember;
use App\Domain\Organization\Actions\UpdateOrganizationName;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\OrganizationInvitation;
use App\Domain\Organization\Models\OrganizationMembership;
use App\Domain\Organization\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateOrganization::class);
        Jetstream::updateTeamNamesUsing(UpdateOrganizationName::class);
        Jetstream::addTeamMembersUsing(AddOrganizationMember::class);
        Jetstream::inviteTeamMembersUsing(InviteOrganizationMember::class);
        Jetstream::removeTeamMembersUsing(RemoveOrganizationMember::class);
        Jetstream::deleteTeamsUsing(DeleteOrganization::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Bind our Organization domain models in place of Jetstream's defaults.
     */
    protected function configureModels(): void
    {
        Jetstream::useTeamModel(Organization::class);
        Jetstream::useMembershipModel(OrganizationMembership::class);
        Jetstream::useTeamInvitationModel(OrganizationInvitation::class);

        Gate::policy(Organization::class, OrganizationPolicy::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     *
     * Maps to Postiz's UserOrganization roles: the organization creator is
     * always treated as a superadmin implicitly via Jetstream's "owner"
     * concept (see HasTeams::ownsTeam()); these three roles are for
     * invited, non-owner members.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::role('superadmin', 'Super Admin', [
            'create',
            'read',
            'update',
            'delete',
        ])->description('Super admins can perform any action, including managing billing and members.');

        Jetstream::role('admin', 'Admin', [
            'create',
            'read',
            'update',
        ])->description('Admins can create, read, and update content, but cannot manage billing or members.');

        Jetstream::role('user', 'User', [
            'read',
        ])->description('Users can view content but cannot make changes.');
    }
}
