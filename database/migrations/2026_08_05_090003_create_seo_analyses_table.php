<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per "Run analysis now" click. No organization_id of its own -
     * inherits tenant isolation transitively through seo_website_id, same
     * pattern as PostComment/PostError through Post.
     */
    public function up(): void
    {
        Schema::create('seo_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_website_id')->constrained()->cascadeOnDelete();
            $table->timestamp('analyzed_at');
            $table->string('title')->nullable();
            $table->string('meta_description')->nullable();
            $table->json('h1s')->nullable();
            $table->json('h2s')->nullable();
            $table->unsignedInteger('desktop_response_ms')->nullable();
            $table->unsignedInteger('mobile_response_ms')->nullable();
            $table->unsignedTinyInteger('desktop_score')->nullable();
            $table->unsignedTinyInteger('mobile_score')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_analyses');
    }
};
