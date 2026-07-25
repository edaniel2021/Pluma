<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A connected social account (a publishing target), distinct from
     * SocialAccount which tracks Google/GitHub *login* linkage. One org can
     * connect several accounts, including several of the same provider
     * (e.g. two different LinkedIn profiles).
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Provider registry key (config/social-providers.php), e.g. 'linkedin', 'x'.
            $table->string('provider');
            // The provider's own ID for the connected account - used to
            // find-or-update on reconnect and to detect duplicate connects.
            $table->string('account_id');
            $table->string('account_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            // Set when a refresh fails or the platform reports the token
            // invalid - surfaces a "reconnect" prompt rather than silently
            // failing every publish attempt.
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            // Scoped per-org (not globally unique) - the same real-world
            // account may legitimately be connected to more than one
            // organization (e.g. an agency managing a shared brand account).
            $table->unique(['organization_id', 'provider', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
