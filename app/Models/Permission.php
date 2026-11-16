<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['slug', 'label', 'group'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /** Every slug in config/permissions.php (no database needed, safe to call while booting). */
    public static function allSlugs(): array
    {
        return collect(config('permissions.groups', []))->flatMap(fn ($items) => array_keys($items))->values()->all();
    }

    /** Make the permissions table match config/permissions.php. Adds new ones, updates labels, removes deleted ones. */
    public static function syncFromConfig(): void
    {
        foreach (config('permissions.groups', []) as $group => $items) {
            foreach ($items as $slug => $label) {
                static::updateOrCreate(['slug' => $slug], ['label' => $label, 'group' => $group]);
            }
        }

        static::whereNotIn('slug', static::allSlugs())->delete();
    }
}
