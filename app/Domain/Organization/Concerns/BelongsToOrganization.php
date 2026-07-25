<?php

namespace App\Domain\Organization\Concerns;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The key multi-tenant data-isolation safety net: every tenant-scoped model
 * (Post, Tag, PostComment, PostError, ...) uses this so queries are
 * auto-filtered to the active organization and new records are auto-tagged
 * with it, without every controller/action having to remember to do so.
 *
 * The scope only applies when an organization is actually resolvable (see
 * CurrentOrganization) - it deliberately does NOT hide rows behind a
 * false-if-missing filter, so console commands, tests, and later
 * cross-tenant admin tooling can still query freely by being explicit.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if ($organizationId = CurrentOrganization::id()) {
                $builder->where($builder->qualifyColumn('organization_id'), $organizationId);
            }
        });

        static::creating(function ($model) {
            if (! $model->organization_id && $organizationId = CurrentOrganization::id()) {
                $model->organization_id = $organizationId;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
