<?php

namespace App\Domain\Seo\Models;

use App\Domain\Organization\Concerns\BelongsToOrganization;
use Database\Factories\SeoWebsiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tracked website - homepage-only crawl scope for now, see
 * RunSiteAnalysis. Mapping to a verified Search Console property
 * (search_console_site_url) is optional.
 */
class SeoWebsite extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'url',
        'search_console_account_id',
        'search_console_site_url',
        'last_analysis_failed_at',
        'last_analysis_error',
        'last_keyword_check_keywords',
        'last_keyword_analysis_completed_at',
        'last_keyword_analysis_failed_at',
        'last_keyword_analysis_error',
    ];

    protected function casts(): array
    {
        return [
            'last_analysis_failed_at' => 'datetime',
            'last_keyword_check_keywords' => 'array',
            'last_keyword_analysis_completed_at' => 'datetime',
            'last_keyword_analysis_failed_at' => 'datetime',
        ];
    }

    public function searchConsoleAccount(): BelongsTo
    {
        return $this->belongsTo(SearchConsoleAccount::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(SeoAnalysis::class);
    }

    public function keywordMetrics(): HasMany
    {
        return $this->hasMany(SeoKeywordMetric::class);
    }

    public function pageAnalyses(): HasMany
    {
        return $this->hasMany(SeoPageAnalysis::class);
    }

    protected static function newFactory(): SeoWebsiteFactory
    {
        return SeoWebsiteFactory::new();
    }
}
