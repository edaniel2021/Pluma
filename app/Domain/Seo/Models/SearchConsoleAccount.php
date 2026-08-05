<?php

namespace App\Domain\Seo\Models;

use App\Domain\Organization\Concerns\BelongsToOrganization;
use Database\Factories\SearchConsoleAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Google account authorized for Search Console's read-only reporting
 * scope. Deliberately not an Integration/SocialProviderContract row - see
 * the migration for why (no "post" concept at all).
 */
class SearchConsoleAccount extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'google_email',
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

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function needsTokenRefresh(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function websites(): HasMany
    {
        return $this->hasMany(SeoWebsite::class);
    }

    protected static function newFactory(): SearchConsoleAccountFactory
    {
        return SearchConsoleAccountFactory::new();
    }
}
