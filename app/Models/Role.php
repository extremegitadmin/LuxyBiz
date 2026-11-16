<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $fillable = ['name', 'description'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Replace this role's permissions with the given slugs. Unknown slugs are ignored. */
    public function syncPermissionSlugs(array $slugs): void
    {
        $this->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id')->all());
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        return $this->permissions()->pluck('slug')->all();
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
