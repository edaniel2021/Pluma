<?php

namespace App\Domain\Seo\Models;

use Database\Factories\SeoKeywordMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw Google Search Console query performance row, refreshed on each
 * analysis run. No BelongsToOrganization of its own - same transitive
 * tenancy pattern as SeoAnalysis. "Trending keywords" is computed on read
 * (see ComputeTrendingKeywords) by comparing two periods' worth of these
 * rows - nothing here is itself a "trend."
 */
class SeoKeywordMetric extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'seo_website_id',
        'query',
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

    protected static function newFactory(): SeoKeywordMetricFactory
    {
        return SeoKeywordMetricFactory::new();
    }
}
