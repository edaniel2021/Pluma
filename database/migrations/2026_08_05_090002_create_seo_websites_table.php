<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tracked website (homepage-only crawl scope for now - no sitemap
     * crawling/link-following yet). Mapping to a verified Search Console
     * property is optional: a website can be tracked for crawl+PageSpeed
     * data alone, before or without ever connecting Search Console.
     */
    public function up(): void
    {
        Schema::create('seo_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->foreignId('search_console_account_id')->nullable()
                ->constrained()->nullOnDelete();
            // Google's exact property identifier, e.g. "https://example.com/"
            // or "sc-domain:example.com" - not always the same string as
            // `url` above, so kept separate rather than derived from it.
            $table->string('search_console_site_url')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_websites');
    }
};
