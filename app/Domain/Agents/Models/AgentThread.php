<?php

namespace App\Domain\Agents\Models;

use App\Domain\Organization\Concerns\BelongsToOrganization;
use App\Models\User;
use Database\Factories\AgentThreadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentThread extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
    ];

    protected static function newFactory(): AgentThreadFactory
    {
        return AgentThreadFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (AgentThread $thread) {
            $thread->user_id ??= auth()->id();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class)->orderBy('created_at');
    }
}
