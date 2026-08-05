<?php

namespace App\Domain\Seo\Models;

use Database\Factories\SeoAnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per "Run analysis now" click. No BelongsToOrganization of its
 * own - inherits tenant isolation transitively through seo_website_id,
 * same pattern as PostComment/PostError through Post.
 */
class SeoAnalysis extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'seo_website_id',
        'analyzed_at',
        'title',
        'meta_description',
        'h1s',
        'h2s',
        'desktop_response_ms',
        'mobile_response_ms',
        'desktop_score',
        'mobile_score',
        'robots_txt_result',
        'sitemap_result',
    ];

    protected function casts(): array
    {
        return [
            'analyzed_at' => 'datetime',
            'h1s' => 'array',
            'h2s' => 'array',
            'robots_txt_result' => 'array',
            'sitemap_result' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(SeoWebsite::class, 'seo_website_id');
    }

    protected static function newFactory(): SeoAnalysisFactory
    {
        return SeoAnalysisFactory::new();
    }
}
