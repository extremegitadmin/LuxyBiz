<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of "who opened which customer document, and when" (Day 23). Rows can be added, never changed or removed from the application. */
class DocumentAccessLog extends Model
{
    public const UPDATED_AT = null;

    /** What staff see for each action. */
    public const ACTIONS = [
        'view' => 'Opened',
        'download' => 'Downloaded',
        'missing' => 'File missing',
        'integrity_failed' => 'Failed integrity check',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
