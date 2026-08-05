<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real production bug: RunSiteAnalysisJob had no failed() handler, so
     * a permanent job failure left the "Run analysis now" button stuck on
     * "Analyzing..." forever - the Livewire component only knew how to
     * detect a *successful* fresh SeoAnalysis row, never a failure, so
     * there was nothing to make it stop waiting.
     */
    public function up(): void
    {
        Schema::table('seo_websites', function (Blueprint $table) {
            $table->timestamp('last_analysis_failed_at')->nullable()->after('search_console_site_url');
            $table->text('last_analysis_error')->nullable()->after('last_analysis_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('seo_websites', function (Blueprint $table) {
            $table->dropColumn(['last_analysis_failed_at', 'last_analysis_error']);
        });
    }
};
