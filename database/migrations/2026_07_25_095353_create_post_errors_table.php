<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Not populated until Phase 3's publishing pipeline exists - the table
     * lands now so the Post model's relations are complete from the start.
     * `integration_id` has no FK constraint yet since the Integration model
     * doesn't exist until the integrations phase; add the constraint then.
     */
    public function up(): void
    {
        Schema::create('post_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->string('type');
            $table->text('message');
            $table->json('raw_response')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_errors');
    }
};
