<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate failure/completion tracking for the keyword-driven page
     * analysis flow, deliberately independent of last_analysis_failed_at/
     * last_analysis_error (those track the homepage "Run analysis now"
     * job, a different async operation with its own timeline).
     * last_keyword_analysis_completed_at is a required marker (not
     * inferred from "does a fresh child row exist") because a legitimately
     * correct result can be zero rank rows - none of the submitted
     * keywords rank for any page.
     */
    public function up(): void
    {
        Schema::table('seo_websites', function (Blueprint $table) {
            $table->json('last_keyword_check_keywords')->nullable()->after('last_analysis_error');
            $table->timestamp('last_keyword_analysis_completed_at')->nullable()->after('last_keyword_check_keywords');
            $table->timestamp('last_keyword_analysis_failed_at')->nullable()->after('last_keyword_analysis_completed_at');
            $table->text('last_keyword_analysis_error')->nullable()->after('last_keyword_analysis_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('seo_websites', function (Blueprint $table) {
            $table->dropColumn([
                'last_keyword_check_keywords',
                'last_keyword_analysis_completed_at',
                'last_keyword_analysis_failed_at',
                'last_keyword_analysis_error',
            ]);
        });
    }
};
