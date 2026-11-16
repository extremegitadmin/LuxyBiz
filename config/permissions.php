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
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.edit' => 'Edit users (including activate / deactivate)',
            'users.delete' => 'Delete users',
        ],
        'Roles' => [
            'roles.view' => 'View roles',
            'roles.create' => 'Create roles',
            'roles.edit' => 'Edit roles and their permissions',
            'roles.delete' => 'Delete roles',
        ],
        'Products (sample module)' => [
            'products.view' => 'View products',
            'products.create' => 'Add products',
            'products.edit' => 'Edit products',
            'products.delete' => 'Delete products',
        ],
    ],
];
