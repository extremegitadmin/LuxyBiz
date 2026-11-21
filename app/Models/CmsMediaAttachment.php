<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CmsMediaAttachment extends Model
{
    protected $table = 'cms_media_attachments';

    protected $fillable = [
        'media_id',
        'attachable_type',
        'attachable_id',
        'collection_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'media_id');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}