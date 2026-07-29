<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-recipient delivery log for a broadcast - lets the UI show which
     * contacts a send succeeded/failed for, individually.
     */
    public function up(): void
    {
        Schema::create('whatsapp_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_contact_id')->constrained()->cascadeOnDelete();
            // pending|sent|failed
            $table->string('status')->default('pending');
            // Meta's returned message ID (wamid), once sent.
            $table->string('message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcast_recipients');
    }
};
