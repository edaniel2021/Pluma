<?php

namespace App\Domain\Marketplace\Models;

use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Low-priority schema stub - see MarketplaceOrder. Inherits tenant
 * isolation transitively through its order, same pattern as
 * PostComment/PostError through Post.
 */
class MarketplaceMessage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'marketplace_order_id',
        'sender_organization_id',
        'content',
    ];

    /**
     * Explicit FK needed: belongsTo() guesses the foreign key from this
     * *method's* name ("order" -> `order_id`), not the related class name,
     * so it would otherwise miss the actual `marketplace_order_id` column.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'sender_organization_id');
    }
}
