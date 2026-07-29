<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A connected WhatsApp Business Cloud API phone number. Deliberately
     * not an Integration/SocialProviderContract - WhatsApp sends targeted
     * broadcast messages to opted-in contacts using pre-approved templates,
     * not "posts" to a feed, so it doesn't fit that abstraction at all.
     *
     * Connected via manual entry (WABA ID / Phone Number ID / permanent
     * access token pasted in after the user completes Meta's Embedded
     * Signup themselves), not an OAuth redirect/callback - Embedded
     * Signup's JS SDK flow needs Meta App Review regardless, so a manual
     * flow is no more limited in practice and far simpler to build.
     */
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('waba_id');
            $table->string('phone_number_id');
            $table->string('display_phone_number')->nullable();
            $table->text('access_token');
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'phone_number_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
