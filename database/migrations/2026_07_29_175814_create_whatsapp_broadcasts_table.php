<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single send of a pre-approved Meta template to a set of recipients
     * (logged individually in whatsapp_broadcast_recipients). Scoped to a
     * WhatsAppAccount, not directly to an organization - see
     * whatsapp_contacts for why.
     */
    public function up(): void
    {
        Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->string('template_name');
            $table->string('template_language')->default('en_US');
            // draft|sending|sent|failed
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcasts');
    }
};
