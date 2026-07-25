<?php

namespace App\Domain\Organization\Support;

use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the "active organization" for tenant-scoped queries.
 *
 * Defaults to the logged-in user's current team (Auth::user()->currentTeam)
 * for the normal web-request case. Background jobs (Phase 3+) won't have an
 * authenticated user, so they must call `set()` explicitly before touching
 * any BelongsToOrganization model, then `clear()` when done.
 */
class CurrentOrganization
{
    protected static ?Organization $override = null;

    public static function set(?Organization $organization): void
    {
        static::$override = $organization;
    }

    public static function clear(): void
    {
        static::$override = null;
    }

    public static function get(): ?Organization
    {
        return static::$override ?? Auth::user()?->currentTeam;
    }

    public static function id(): ?int
    {
        return static::get()?->id;
    }
}
