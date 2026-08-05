<?php

namespace App\Domain\Organization\Models;

use App\Domain\Agents\Models\AgentThread;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Seo\Models\SearchConsoleAccount;
use App\Domain\Seo\Models\SeoWebsite;
use App\Domain\WhatsApp\Models\WhatsAppAccount;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Postiz's Organization, rebuilt on top of Jetstream's Team primitive.
 *
 * Jetstream's HasTeams trait and base Team class hardcode a handful of
 * column names (`user_id` for the owner FK, `personal_team`, and
 * `current_team_id` on the users table) across several methods, so those
 * stay as-is even though this model and its table are named for our own
 * domain (see database/migrations/*_create_organizations_table.php).
 *
 * Also the owner of the shared media library (Postiz's Media model is
 * org-level, not tied to any single post) via the 'library' collection.
 */
class Organization extends JetstreamTeam implements HasMedia
{
    use Billable;

    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'timezone',
        'personal_team',
        'subscription_tier',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * Laravel's factory-name convention only strips a leading `App\Models\`
     * segment, so a model living under `App\Domain\...\Models\` needs this
     * override or it guesses `Database\Factories\Domain\...\OrganizationFactory`.
     */
    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function whatsAppAccounts(): HasMany
    {
        return $this->hasMany(WhatsAppAccount::class);
    }

    public function agentThreads(): HasMany
    {
        return $this->hasMany(AgentThread::class);
    }

    public function searchConsoleAccounts(): HasMany
    {
        return $this->hasMany(SearchConsoleAccount::class);
    }

    public function seoWebsites(): HasMany
    {
        return $this->hasMany(SeoWebsite::class);
    }
}
