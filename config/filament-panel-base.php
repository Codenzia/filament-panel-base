<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Panel IDs
    |--------------------------------------------------------------------------
    |
    | Define the panel IDs used in your application. These are used by
    | BasePanelProvider for cross-panel navigation links.
    |
    */
    'panels' => ['admin', 'dashboard'],

    /*
    |--------------------------------------------------------------------------
    | Admin Role
    |--------------------------------------------------------------------------
    |
    | The role name used to identify super admins for notifications and
    | access checks.
    |
    */
    'admin_role' => 'super_admin',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of your User model.
    |
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Locale Settings
    |--------------------------------------------------------------------------
    |
    | Configure locale detection and available locales.
    | 'provider' should be a class implementing ProvidesLocales contract.
    |
    */
    'locale' => [
        'provider' => null,
        'available' => ['en'],
        'detection_order' => ['session', 'cookie', 'config'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Country Detection
    |--------------------------------------------------------------------------
    |
    | Configure automatic country detection from IP.
    | 'model' should be the class name of your Country model.
    |
    */
    'country' => [
        'auto_detect' => true,
        'default_id' => null,
        'model' => null,
        'geo_api' => 'https://ipapi.co/{ip}/json/',
        'cache_ttl' => 1440,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    |
    | 'model' should be the class name of your Currency model.
    |
    */
    'currency' => [
        'model' => null,
        'virtual_usd' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Validation
    |--------------------------------------------------------------------------
    |
    | Settings for contact form validation rules.
    |
    */
    'contact_validation' => [
        'require_whatsapp' => false,
        'allow_email_alternative' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding Settings Resolver
    |--------------------------------------------------------------------------
    |
    | A callback or class that resolves branding settings (brand name, logo,
    | favicon, colors). Set to null to use defaults from config.
    |
    | If a class is provided, it should have these methods:
    |   - getAppLogoUrl(): ?string
    |   - getAppFaviconUrl(): ?string
    |   - app_name: string (property)
    |   - primary_color, secondary_color, etc.: string (properties)
    |
    */
    'settings_class' => null,

    /*
    |--------------------------------------------------------------------------
    | Default Colors
    |--------------------------------------------------------------------------
    |
    | Default color palette when no settings class is configured.
    | Values should be hex color codes.
    |
    */
    'colors' => [
        'primary' => '#3b82f6',
        'secondary' => '#6366f1',
        'danger' => '#ef4444',
        'warning' => '#f59e0b',
        'success' => '#10b981',
        'info' => '#06b6d4',
    ],
];
