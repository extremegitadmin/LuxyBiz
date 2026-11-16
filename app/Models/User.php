<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    
    // role_id, is_super and is_active are NOT fillable: set them explicitly with forceFill().
    protected $fillable = ['name', 'email', 'password', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        if ($this->is_super) {
            return Permission::allSlugs();
        }

        return $this->permissionCache ??= $this->role ? $this->role->permissionSlugs() : [];
    }

    /** True when the user holds ANY of the given permissions. The Super Admin always passes. */
    public function hasPermission(string ...$slugs): bool
    {
        if ($this->is_super || $slugs === []) {
            return true;
        }

        return array_intersect($slugs, $this->permissionSlugs()) !== [];
    }

    public function roleLabel(): string
    {
        return $this->is_super ? 'Super Admin' : ($this->role?->name ?? 'No role');
    }
}