<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view employee',
            'create employee',
            'edit employee',
            'delete employee',

            'view department',
            'create department',
            'edit department',
            'delete department',

            'view users',
            'create users',
            'edit users',
            'delete users',

            'manage roles',
            'manage permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Create roles
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $staff = Role::firstOrCreate([
            'name' => 'staff',
            'guard_name' => 'web',
        ]);

        // Admin gets all permissions
        $admin->syncPermissions($permissions);

        // Staff gets selected permissions
        $staff->syncPermissions([
            'view employee',
            'create employee',
            'edit employee',
            'view department',
        ]);
    }
}