<?php

namespace App\Domain\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppBroadcastRecipient extends Model
{
    /**
     * Eloquent's naming convention would snake_case this to
     * `whats_app_broadcast_recipients` - explicit override to keep the
     * actual table name readable.
     *
     * @var string
     */
    protected $table = 'whatsapp_broadcast_recipients';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_broadcast_id',
        'whatsapp_contact_id',
        'status',
        'message_id',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBroadcast::class, 'whatsapp_broadcast_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }
}
