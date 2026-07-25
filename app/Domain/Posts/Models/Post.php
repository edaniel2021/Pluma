<?php

namespace App\Domain\Posts\Models;

use App\Domain\Organization\Concerns\BelongsToOrganization;
use App\Domain\Posts\Enums\PostState;
use App\Models\User;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use BelongsToOrganization;
    use HasFactory;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'content',
        'state',
        'scheduled_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => PostState::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->user_id ??= auth()->id();
            $post->state ??= PostState::Draft;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(PostError::class);
    }
}
