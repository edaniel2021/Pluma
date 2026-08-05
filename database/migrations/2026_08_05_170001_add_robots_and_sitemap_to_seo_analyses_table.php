<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * robots.txt/sitemap.xml checks are folded into the existing
     * "Run analysis now" flow (RunSiteAnalysis::execute()) rather than a
     * separate row - they're two small, always-on file fetches, not part
     * of the page-crawl-count concern the keyword-driven flow exists to
     * bound.
     */
    public function up(): void
    {
        Schema::table('seo_analyses', function (Blueprint $table) {
            $table->json('robots_txt_result')->nullable()->after('h2s');
            $table->json('sitemap_result')->nullable()->after('robots_txt_result');
        });
    }

    public function down(): void
    {
        Schema::table('seo_analyses', function (Blueprint $table) {
            $table->dropColumn(['robots_txt_result', 'sitemap_result']);
        });
    }
};
