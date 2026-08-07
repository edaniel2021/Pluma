<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * provider_post_id is the platform's own identifier for the published
     * post (e.g. Facebook's post ID) - captured once at publish time via
     * SocialProviderContract::post()'s return value, needed to later look
     * up engagement for that specific post. Nullable: only posts that have
     * actually published successfully have one, and most providers don't
     * support fetching engagement at all yet (see
     * AbstractSocialProvider::fetchEngagement()'s default).
     *
     * likes_count/comments_count/shares_count are a snapshot, not a
     * history - refreshed in place by posts:refresh-engagement, same
     * "latest known value" shape as SeoWebsite's last_analysis_* columns,
     * not a separate time-series table, since no trend-over-time feature
     * is requested for this data.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('provider_post_id')->nullable()->after('published_at');
            $table->unsignedInteger('likes_count')->nullable()->after('provider_post_id');
            $table->unsignedInteger('comments_count')->nullable()->after('likes_count');
            $table->unsignedInteger('shares_count')->nullable()->after('comments_count');
            $table->timestamp('engagement_fetched_at')->nullable()->after('shares_count');
            $table->text('engagement_fetch_error')->nullable()->after('engagement_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'provider_post_id',
                'likes_count',
                'comments_count',
                'shares_count',
                'engagement_fetched_at',
                'engagement_fetch_error',
            ]);
        });
    }
};
