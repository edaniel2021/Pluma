<?php

namespace App\Domain\WhatsApp\Models;

use Database\Factories\WhatsAppContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppContact extends Model
{
    use HasFactory;

    /**
     * Eloquent's naming convention would snake_case this to
     * `whats_app_contacts` - explicit override to keep the actual table
     * name readable.
     *
     * @var string
     */
    protected $table = 'whatsapp_contacts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_account_id',
        'phone_number',
        'name',
        'opted_in_at',
    ];

    protected function casts(): array
    {
        return [
            'opted_in_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    protected static function newFactory(): WhatsAppContactFactory
    {
        return WhatsAppContactFactory::new();
    }
}
