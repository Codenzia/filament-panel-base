<?php

return [
    // Shared — analytics tables absent
    'not_migrated_heading' => 'Analytics tables not migrated',
    'not_migrated_description' => 'Run php artisan migrate to create the analytics tables.',

    // Shared — selected range, interpolated into every widget heading
    'range_24h' => 'last 24 hours',
    'range_7d' => 'last 7 days',
    'range_30d' => 'last 30 days',
    'range_90d' => 'last 90 days',

    // Page views chart
    'visitors_heading' => 'Page views — :range',
    'visitors_dataset' => 'Page views',
    'visitors_empty_heading' => 'No page views yet',
    'visitors_empty_description' => 'Page views will appear here once visitors start browsing your site.',

    // Device types chart
    'devices_heading' => 'Device types — :range',
    'devices_dataset' => 'Page views',
    'devices_empty_heading' => 'No device data yet',
    'devices_empty_description' => 'Device breakdown will appear here once visitors start browsing your site.',
    'device_desktop' => 'Desktop',
    'device_mobile' => 'Mobile',
    'device_tablet' => 'Tablet',
    'device_unknown' => 'Unknown',

    // Failed logins chart
    'failed_logins_heading' => 'Failed logins — :range',
    'failed_logins_dataset' => 'Failed logins',
    'failed_logins_description' => 'A sustained spike usually indicates a credential-stuffing run.',
    'failed_logins_empty_heading' => 'No failed logins',
    'failed_logins_empty_description' => 'No failed login attempts were recorded for this period.',

    // Signup funnel
    'auth_funnel_heading' => 'Signup funnel — :range',

    // Error stats
    'errors_heading' => 'Errors — :range',

    // List widgets
    'top_pages_description' => 'Most-visited routes in the :range.',
    'slowest_pages_description' => 'Top routes by average server duration in the :range.',
    'geo_description' => 'Visitor origin by country in the :range.',
];
