<?php

namespace App\Domain\Marketplace\Models;

use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Low-priority schema stub for Postiz's Marketplace feature (an organization
 * hiring another to manage/create content) - see the migration docblock for
 * why there's no BelongsToOrganization here. Schema-only for now: no
 * actions, policies, or UI.
 */
class MarketplaceOrder extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'buyer_organization_id',
        'seller_organization_id',
        'title',
        'description',
        'amount',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'buyer_organization_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'seller_organization_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketplaceMessage::class);
    }
}
