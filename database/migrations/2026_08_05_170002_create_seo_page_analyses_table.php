<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per unique page URL discovered via a keyword-driven check
     * (RunKeywordPageAnalysis) - not a blanket sitemap crawl, see
     * CLAUDE.md's SEO section. page_url is `text`, not `string(255)`,
     * proactively avoiding the exact truncation bug already hit once on
     * seo_analyses.title/meta_description (GSC page URLs can carry long
     * query strings). crawled_at is nullable - null means "ranked but
     * over the per-run crawl cap," not "not yet processed."
     */
    public function up(): void
    {
        Schema::create('seo_page_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_website_id')->constrained()->cascadeOnDelete();
            $table->text('page_url');
            $table->text('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('h1s')->nullable();
            $table->json('h2s')->nullable();
            $table->timestamp('crawled_at')->nullable();
            $table->text('crawl_error')->nullable();
            $table->timestamps();

            $table->unique(['seo_website_id', 'page_url'], 'seo_page_analyses_website_url_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_page_analyses');
    }
};
