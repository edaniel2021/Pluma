<?php

namespace App\Domain\Seo\Models;

use Database\Factories\SeoPageAnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per unique page URL discovered by a keyword-driven check
 * (RunKeywordPageAnalysis) - not a blanket sitemap crawl. No
 * BelongsToOrganization of its own - same transitive tenancy pattern as
 * SeoAnalysis/SeoKeywordMetric through seo_website_id. crawled_at null
 * means this page ranked for a submitted keyword but fell outside the
 * per-run crawl cap, not that it hasn't been processed yet.
 */
class SeoPageAnalysis extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'seo_website_id',
        'page_url',
        'title',
        'meta_description',
        'h1s',
        'h2s',
        'crawled_at',
        'crawl_error',
    ];

    protected function casts(): array
    {
        return [
            'h1s' => 'array',
            'h2s' => 'array',
            'crawled_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(SeoWebsite::class, 'seo_website_id');
    }

    public function keywordRanks(): HasMany
    {
        return $this->hasMany(SeoKeywordPageRank::class);
    }

    protected static function newFactory(): SeoPageAnalysisFactory
    {
        return SeoPageAnalysisFactory::new();
    }
}
