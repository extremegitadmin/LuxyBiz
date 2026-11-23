<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of "who looked at or changed a traveler's health information, and when" (Day 25). Rows can be added, never changed or removed from the application. */
class HealthAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = ['view' => 'Viewed', 'edit' => 'Changed'];

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
