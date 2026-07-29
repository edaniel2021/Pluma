<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scoped to a WhatsAppAccount, not directly to an organization -
     * mirrors PostComment/PostError inheriting tenant isolation
     * transitively through their parent rather than having their own
     * organization_id.
     */
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');
            $table->string('name')->nullable();
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_account_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
