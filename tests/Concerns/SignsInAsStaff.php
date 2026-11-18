<?php

namespace Tests\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Signs a test in through the real login rules (auth + permission middleware, roles and permissions).
 * A Super Admin passes everything. userWith() gets exactly the permissions you list.
 */
trait SignsInAsStaff
{
    protected function superAdmin(string $email = 'super@example.test'): User
    {
        return User::forceCreate([
            'name' => 'Super Admin', 'email' => $email, 'password' => bcrypt('password-12345'),
            'is_super' => true, 'is_active' => true,
        ]);
    }

    /** @param list<string> $permissions */
    protected function userWith(array $permissions, string $email = 'staff@example.test', string $roleName = 'Tester'): User
    {
        Permission::syncFromConfig();
        $role = Role::firstOrNew(['name' => $roleName]);
        if (! $role->exists) {
            $role->slug = Role::uniqueSlug($roleName);
            $role->save();
        }
        $role->syncPermissionSlugs($permissions);

        return User::forceCreate([
            'name' => 'Staff', 'email' => $email, 'password' => bcrypt('password-12345'),
            'role_id' => $role->id, 'is_super' => false, 'is_active' => true,
        ]);
    }

    protected function signInAsSuperAdmin(): User
    {
        return tap($this->superAdmin(), fn ($u) => $this->actingAs($u));
    }
}
