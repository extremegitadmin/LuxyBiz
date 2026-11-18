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
        'Bookings' => [
            'admin.bookings.view' => 'View bookings (list and detail)',
            'admin.bookings.edit' => 'Edit bookings',
            'admin.bookings.export' => 'Export bookings to CSV',
        ],
        'Payments' => [
            'admin.payments.view' => 'View payments (list and detail)',
            'admin.payments.events.view' => 'View payment webhook events',
            'admin.payments.link.create' => 'Create online payment links for a booking',
        ],
        'Payment settings' => [
            'admin.settings.gateway.view' => 'View payment gateway settings',
            'admin.settings.gateway.edit' => 'Change payment gateway settings (keys, routing, test connection)',
        ],
        // 'Products (sample module)' => [
        //     'products.view' => 'View products',
        //     'products.create' => 'Add products',
        //     'products.edit' => 'Edit products',
        //     'products.delete' => 'Delete products',
        // ],
    ],
];
