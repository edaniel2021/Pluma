<?php

namespace App\Domain\WhatsApp\Models;

use App\Domain\Organization\Concerns\BelongsToOrganization;
use Database\Factories\WhatsAppAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A connected WhatsApp Business Cloud API phone number - see the migration
 * for why this isn't an Integration/SocialProviderContract.
 */
class WhatsAppAccount extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    /**
     * Eloquent's naming convention would snake_case this to
     * `whats_app_accounts` - explicit override to keep the actual table
     * name readable.
     *
     * @var string
     */
    protected $table = 'whatsapp_accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'waba_id',
        'phone_number_id',
        'display_phone_number',
        'access_token',
        'connected_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WhatsAppContact::class, 'whatsapp_account_id');
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(WhatsAppBroadcast::class, 'whatsapp_account_id');
    }

    protected static function newFactory(): WhatsAppAccountFactory
    {
        return WhatsAppAccountFactory::new();
    }
}
