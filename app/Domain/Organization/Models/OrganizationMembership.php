<?php

namespace App\Domain\Organization\Models;

use Laravel\Jetstream\Membership as JetstreamMembership;

class OrganizationMembership extends JetstreamMembership
{
    /**
     * The table associated with the pivot model.
     *
     * Jetstream's base Membership class hardcodes $table = 'team_user',
     * so this override is required, not cosmetic.
     *
     * @var string
     */
    protected $table = 'organization_user';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
}
