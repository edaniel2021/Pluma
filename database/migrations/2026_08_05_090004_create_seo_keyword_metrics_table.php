<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw Google Search Console query performance rows, refreshed on each
     * analysis run. No organization_id of its own - same transitive
     * tenancy pattern as seo_analyses. "Trending keywords" is computed on
     * read by comparing two periods' worth of these rows, not stored.
     */
    public function up(): void
    {
        Schema::create('seo_keyword_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_website_id')->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');
            $table->decimal('ctr', 8, 4);
            $table->decimal('position', 8, 2);
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('pulled_at');
            $table->timestamps();

            $table->index(['seo_website_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_metrics');
    }
};
