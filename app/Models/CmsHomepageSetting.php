<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsHomepageSetting extends Model
{
    protected $table = 'cms_homepage_settings';

    protected $fillable = [
        'hero_eyebrow',
        'hero_title',
        'hero_subtitle',
        'hero_media_id',
        'hero_cta_text',
        'hero_cta_url',
        'hero_secondary_cta_text',
        'hero_secondary_cta_url',
        'featured_tour_ids',
        'featured_section_title',
        'featured_section_description',
        'trust_section_title',
        'trust_section_description',
        'cta_title',
        'cta_description',
        'cta_button_text',
        'cta_button_url',
    ];

    protected function casts(): array
    {
        return [
            'featured_tour_ids' => 'array',
        ];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'hero_media_id');
    }
}