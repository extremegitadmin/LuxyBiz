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
            'admin.payments.events.view' => 'View payment activity',
            'admin.payments.link.create' => 'Create online payment links for a booking',
        ],
        'Payment settings' => [
            'admin.settings.gateway.view' => 'View payment gateway settings',
            'admin.settings.gateway.edit' => 'Change payment gateway settings (keys, routing, test connection)',
        ],
        'Cancellation settings' => [
            'admin.settings.cancellation.view' => 'View cancellation rules',
            'admin.settings.cancellation.edit' => 'Change cancellation rules (refund percentages, credit, fees)',
        ],
        'Cancellations' => [
            'admin.cancellations.view' => 'View cancellation requests',
            'admin.cancellations.override' => 'Change the refund amount of a cancellation request (reason required)',
            'admin.cancellations.decide' => 'Approve or reject cancellation requests (emails the customer)',
            'admin.cancellations.complete' => 'Mark a refund as paid or issue travel credit (cancels the booking)',
        ],
         'Customer documents' => [
            'admin.documents.view' => 'See the list of documents on a booking (passport, visa, tickets, insurance)',
            'admin.documents.download' => 'Download a copy of a customer document (every download is logged)',
            'admin.documents.upload' => 'Upload customer documents',
            'admin.documents.delete' => 'Delete customer documents',
            'admin.documents.access_log' => 'See who opened or downloaded the documents of a booking',
        ],
         'Health and intake' => [
            'admin.health.view' => 'See the health and intake information of a booking (restricted; every view is logged)',
            'admin.health.edit' => 'Change the health notes of a traveler (logged)',
            'admin.health.access_log' => 'See who viewed or changed the health information of a booking',
        ],
        'Assisted bookings' => [
            'admin.assisted.view' => 'See the list of bookings made by staff for customers',
            'admin.assisted.create' => 'Make a booking for a customer (search customers, create a customer, choose tour and departure)',
        ],

        'Tour Categories' => [
            'admin.tour-categories.view' => 'View tour categories',
            'admin.tour-categories.create' => 'Create tour categories',
            'admin.tour-categories.edit' => 'Edit tour categories',
            'admin.tour-categories.delete' => 'Delete tour categories',
        ],

        'Tours' => [
            'admin.tours.view' => 'View tours',
            'admin.tours.create' => 'Create tours',
            'admin.tours.edit' => 'Edit tours',
            'admin.tours.delete' => 'Delete tours',
            'admin.tours.preview' => 'Preview tours',
            'admin.tours.duplicate' => 'Duplicate tours',
            'admin.tours.publish' => 'Publish or unpublish tours',
            'admin.tours.permanent-delete' => 'Permanently delete tours',
            'admin.tours.itinerary.view' => 'View tour itineraries',
            'admin.tours.itinerary.create' => 'Add itinerary days',
            'admin.tours.itinerary.edit' => 'Edit itinerary days',
            'admin.tours.itinerary.delete' => 'Delete itinerary days',
            'admin.tours.gallery.delete' => 'Delete tour gallery images',
        ],
        'Tour Packages' => [
            'admin.tour-packages.view' => 'View tour packages',
            'admin.tour-packages.create' => 'Create tour packages',
            'admin.tour-packages.edit' => 'Edit tour packages',
            'admin.tour-packages.delete' => 'Delete tour packages',
        ],

        'Objectives' => [
            'admin.objectives.view' => 'View objectives',
            'admin.objectives.create' => 'Create objectives',
            'admin.objectives.edit' => 'Edit objectives',
            'admin.objectives.delete' => 'Delete objectives',
        ],
        'Customers' => [
            'admin.customers.view' => 'See the Customers menu: the list of customers, their bookings and (with the documents permission) their documents',
        ],

        // 'Departures' => [
        //     'admin.departures.view' => 'View departures',
        //     'admin.departures.create' => 'Create departures',
        //     'admin.departures.edit' => 'Edit departures',
        //     'admin.departures.delete' => 'Delete departures',
        //     'admin.departures.toggle' => 'Activate or deactivate departures',
        // ],

        // 'Departure Operations' => [
        //     'admin.departure-operations.view' => 'View departure operations',
        //     'admin.departure-operations.manifest' => 'View departure manifest',
        //     'admin.departure-operations.sales.toggle' => 'Enable or disable departure sales',
        // ],

        'CMS Pages' => [
            'admin.cms.pages.view' => 'View CMS pages',
            'admin.cms.pages.create' => 'Create CMS pages',
            'admin.cms.pages.edit' => 'Edit CMS pages',
            'admin.cms.pages.delete' => 'Delete CMS pages',
            'admin.cms.pages.publish' => 'Publish or unpublish CMS pages',
        ],

        'Homepage' => [
            'admin.cms.homepage.view' => 'View homepage settings',
            'admin.cms.homepage.edit' => 'Edit homepage content and sections',
        ],

        'Testimonials' => [
            'admin.cms.testimonials.view' => 'View testimonials',
            'admin.cms.testimonials.create' => 'Create testimonials',
            'admin.cms.testimonials.edit' => 'Edit testimonials',
            'admin.cms.testimonials.delete' => 'Delete testimonials',
            'admin.cms.testimonials.reorder' => 'Reorder testimonials',
        ],

        'Trust Items' => [
            'admin.cms.trust-items.view' => 'View trust items',
            'admin.cms.trust-items.create' => 'Create trust items',
            'admin.cms.trust-items.edit' => 'Edit trust items',
            'admin.cms.trust-items.delete' => 'Delete trust items',
            'admin.cms.trust-items.reorder' => 'Reorder trust items',
        ],

        'Menus and Footer' => [
            'admin.cms.menus.view' => 'View menus',
            'admin.cms.menus.edit' => 'Edit menus and menu items',
        ],

        'Site Settings' => [
            'admin.cms.site-settings.view' => 'View site settings',
            'admin.cms.site-settings.edit' => 'Edit site settings and footer details',
        ],

        'Media Library' => [
            'admin.media.view' => 'View and search media library',
            'admin.media.upload' => 'Upload media files',
            'admin.media.replace' => 'Replace media files',
            'admin.media.delete' => 'Delete media files',
            'admin.media.alt-text.edit' => 'Edit media alt text and captions',
            'admin.media.where-used.view' => 'View where media files are used',
        ],

        'Insurance' => [
            'admin.insurance.view'   => 'View Insurance options',
            'admin.insurance.create' => 'Create Insurance options',
            'admin.insurance.edit'   => 'Edit Insurance options',
            'admin.insurance.delete' => 'Delete Insurance options',
        ],


        // 'Products (sample module)' => [
        //     'products.view' => 'View products',
        //     'products.create' => 'Add products',
        //     'products.edit' => 'Edit products',
        //     'products.delete' => 'Delete products',
        // ],
    ],
];
