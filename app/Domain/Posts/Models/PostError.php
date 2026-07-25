<?php

namespace App\Domain\Posts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not populated until Phase 3's publishing pipeline exists - see the
 * migration for why `integration_id` has no FK constraint yet.
 */
class PostError extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'post_id',
        'integration_id',
        'type',
        'message',
        'raw_response',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
