<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (keyword, page) pair returned by Search Console's
     * page-dimension query for a user-submitted keyword. seo_website_id is
     * denormalized (duplicated from the parent seo_page_analyses row) so
     * the delete-and-replace step doesn't need a join - same trade-off
     * seo_keyword_metrics already makes.
     */
    public function up(): void
    {
        Schema::create('seo_keyword_page_ranks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_page_analysis_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');
            $table->decimal('ctr', 8, 4);
            $table->decimal('position', 8, 2);
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('pulled_at');
            $table->timestamps();

            $table->index(['seo_website_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_page_ranks');
    }
};
