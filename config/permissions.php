<?php

/**
 * Every permission in the system, grouped for the role screen.
 * 'slug' => 'Label shown to the admin'.
 *
 * To add a module: add its permissions here, run  php artisan permissions:sync,
 * guard its routes with ->middleware('permission:slug') and its buttons with @can('slug').
 * New permissions are OFF for every role until you tick them. The Super Admin always has everything.
 */
return [
    'groups' => [
        'Users' => [
            'admin.users.view' => 'View users',
            'admin.users.create' => 'Create users',
            'admin.users.edit' => 'Edit users (including activate / deactivate)',
            'admin.users.delete' => 'Delete users',
        ],
        'Roles' => [
            'admin.roles.view' => 'View roles',
            'admin.roles.create' => 'Create roles',
            'admin.roles.edit' => 'Edit roles and their permissions',
            'admin.roles.delete' => 'Delete roles',
        ],
        // 'Products (sample module)' => [
        //     'products.view' => 'View products',
        //     'products.create' => 'Add products',
        //     'products.edit' => 'Edit products',
        //     'products.delete' => 'Delete products',
        // ],
    ],
];
