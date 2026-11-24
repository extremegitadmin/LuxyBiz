<?php

/**
 * Booking rules shared by the staff (assisted) booking form and, later, the public website checkout.
 * PLACEHOLDER VALUES until the client confirms them (open questions: insurance price, maximum group size).
 */
return [
    // Travel insurance price per traveler, in each currency. Placeholder (85 USD is what the demo data uses).
    'insurance_per_traveler' => [
        'USD' => (float) env('BOOKING_INSURANCE_USD', 85),
        'INR' => (float) env('BOOKING_INSURANCE_INR', 6500),
        'EUR' => (float) env('BOOKING_INSURANCE_EUR', 80),
        'GBP' => (float) env('BOOKING_INSURANCE_GBP', 70),
    ],

    // Most travelers one booking may hold. A bigger group is booked as several bookings or by the owner.
    'max_travelers' => (int) env('BOOKING_MAX_TRAVELERS', 10),

    // Travel credit instead of a refund. The client said refunds only, so this is off. The code stays in case it is needed later.
    'credits_enabled' => (bool) env('BOOKING_CREDITS_ENABLED', false),

    // Booking closes this many days before departure (client: 5). A tour can set its own number (tours.booking_cutoff_days).
    'cutoff_days' => (int) env('BOOKING_CUTOFF_DAYS', 5),

    // How long a customer payment link (Day 29) stays usable, in hours.
    'payment_link_hours' => (int) env('BOOKING_PAYMENT_LINK_HOURS', 72),
];
