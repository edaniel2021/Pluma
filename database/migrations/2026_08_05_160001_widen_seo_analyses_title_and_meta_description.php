<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Real production error: SQLSTATE[22001] "value too long for type
     * character varying(255)" inserting a real site's actual <title>/meta
     * description - real pages routinely exceed the 155-160 character
     * "recommended" meta description length, sometimes by a lot, and
     * there's no legitimate reason to truncate what was actually crawled
     * (an overly-long meta description is itself a useful SEO finding to
     * surface, not something to silently cut). Raw ALTER TABLE rather
     * than Blueprint::change() - doctrine/dbal isn't installed, and this
     * codebase avoids adding a dependency for something this simple.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE seo_analyses ALTER COLUMN title TYPE text');
        DB::statement('ALTER TABLE seo_analyses ALTER COLUMN meta_description TYPE text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE seo_analyses ALTER COLUMN title TYPE varchar(255)');
        DB::statement('ALTER TABLE seo_analyses ALTER COLUMN meta_description TYPE varchar(255)');
    }
};
