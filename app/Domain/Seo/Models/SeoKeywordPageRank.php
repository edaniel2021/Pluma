<?php

namespace App\Domain\Seo\Models;

use Database\Factories\SeoKeywordPageRankFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (keyword, page) pair returned by Search Console's
 * page-dimension query for a user-submitted keyword. No
 * BelongsToOrganization of its own - same transitive tenancy pattern as
 * SeoAnalysis/SeoKeywordMetric through seo_website_id. seo_website_id is
 * denormalized (duplicated from the parent SeoPageAnalysis row) purely so
 * the delete-and-replace step doesn't need a join, same trade-off
 * SeoKeywordMetric already makes.
 */
class SeoKeywordPageRank extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'seo_website_id',
        'seo_page_analysis_id',
        'keyword',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'period_start',
        'period_end',
        'pulled_at',
    ];

    protected function casts(): array
    {
        return [
            'ctr' => 'float',
            'position' => 'float',
            'period_start' => 'date',
            'period_end' => 'date',
            'pulled_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(SeoWebsite::class, 'seo_website_id');
    }

    public function pageAnalysis(): BelongsTo
    {
        return $this->belongsTo(SeoPageAnalysis::class, 'seo_page_analysis_id');
    }

    protected static function newFactory(): SeoKeywordPageRankFactory
    {
        return SeoKeywordPageRankFactory::new();
    }
}
