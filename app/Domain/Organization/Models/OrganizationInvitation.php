<?php

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;

class OrganizationInvitation extends JetstreamTeamInvitation
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'role',
    ];

    /**
     * Get the organization that the invitation belongs to.
     *
     * Kept named `team()` (rather than `organization()`) because Jetstream's
     * own TeamInvitationController and invitation email view call
     * `$invitation->team` directly and aren't ours to change.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Jetstream::teamModel(), 'organization_id');
    }
}
