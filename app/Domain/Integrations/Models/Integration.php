<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Organization\Concerns\BelongsToOrganization;
use App\Domain\Posts\Models\Post;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A connected social account available as a publishing target. Distinct
 * from App\Domain\Auth\Models\SocialAccount, which tracks Google/GitHub
 * *login* linkage - don't conflate the two.
 */
class Integration extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'account_id',
        'account_name',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'disabled_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): IntegrationFactory
    {
        return IntegrationFactory::new();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function needsTokenRefresh(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
