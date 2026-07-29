<?php

namespace App\Domain\WhatsApp\Models;

use Database\Factories\WhatsAppBroadcastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppBroadcast extends Model
{
    use HasFactory;

    /**
     * Eloquent's naming convention would snake_case this to
     * `whats_app_broadcasts` - explicit override to keep the actual table
     * name readable.
     *
     * @var string
     */
    protected $table = 'whatsapp_broadcasts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_account_id',
        'template_name',
        'template_language',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppBroadcastRecipient::class, 'whatsapp_broadcast_id');
    }

    protected static function newFactory(): WhatsAppBroadcastFactory
    {
        return WhatsAppBroadcastFactory::new();
    }
}
