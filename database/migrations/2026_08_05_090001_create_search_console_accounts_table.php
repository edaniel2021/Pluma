<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A connected Google account authorized for Search Console's read-only
     * reporting scope. Deliberately not an Integration/SocialProviderContract
     * row - Search Console has no "post" concept at all (it's pure
     * reporting), unlike every other row in the integrations table.
     */
    public function up(): void
    {
        Schema::create('search_console_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('google_email')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            // Set when a refresh fails or Google reports the token invalid -
            // same reconnect-prompt pattern as Integration::disabled_at.
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_accounts');
    }
};
