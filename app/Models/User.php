<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Auth\Models\SocialAccount;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Passport\Client as OAuthClient;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Deliberately not in $fillable - gates the platform-wide /admin
            // panel and must only ever be set directly (tinker/seeder), not
            // via mass assignment.
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * The Google/GitHub social login accounts linked to this user.
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Third-party OAuth apps (Laravel Passport clients) this user has
     * registered - see App\Livewire\Developers\Apps. Must be named exactly
     * `oauthApps()`: Passport's own ClientRepository::create() calls
     * `$user->oauthApps()->forceCreate(...)` directly (oauth_clients has no
     * `user_id` column, only a polymorphic `owner_id`/`owner_type` pair, so
     * it can't just guess a relation name the way Eloquent normally would).
     *
     * Deliberately a plain relation rather than pulling in Passport's own
     * HasApiTokens trait: that trait's tokens()/tokenCan()/createToken()/
     * withAccessToken() method names collide with Laravel\Sanctum\HasApiTokens
     * above, already used for first-party personal access tokens. No
     * collision in practice, though - Sanctum's tokenCan()/withAccessToken()/
     * currentAccessToken() just call ->can($ability) on whatever token object
     * is set, and Passport's own AccessToken/Token/TransientToken classes all
     * implement that too, so Passport's TokenGuard authenticates against this
     * model fine without its trait.
     */
    public function oauthApps(): MorphMany
    {
        return $this->morphMany(OAuthClient::class, 'owner');
    }
}
