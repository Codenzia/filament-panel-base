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

    /*
    |--------------------------------------------------------------------------
    | Theme System
    |--------------------------------------------------------------------------
    |
    | Configure the frontend theme for CSS custom property injection.
    | 'preset' selects a predefined color palette from ThemePresets.
    | 'colors' allows overriding individual color keys
    | (see ThemePresets::colorKeys()).
    |
    | When a settings class implements ProvidesThemeColors, these config
    | values are ignored in favor of the database-driven settings.
    |
    */
    'theme' => [
        'preset' => 'ocean_blue',
        'colors' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Manager
    |--------------------------------------------------------------------------
    |
    | Configuration for the built-in Translation Manager UI.
    | Enable per-panel with ->withTranslations() on the plugin.
    |
    | 'scan_paths' defaults to [app_path(), resource_path('views')].
    | Set to an array of paths to override.
    |
    | 'scan_extensions' controls which file types the scanner reads.
    | Default: ['php']. Add 'js', 'ts', 'vue' for frontend files.
    |
    | 'scan_functions' adds extra function names the scanner looks for
    | as JSON translation calls (in addition to the built-in __()).
    | Common JS examples: '$t' (vue-i18n), 'i18n.t' (i18next).
    |
    */
    'translations' => [
        'navigation_group' => 'Settings',
        'navigation_sort' => 11,
        'navigation_icon' => 'heroicon-o-language',
        'scan_paths' => null,
        'scan_extensions' => ['php'],
        'scan_functions' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Module
    |--------------------------------------------------------------------------
    |
    | Static defaults for the Auth module. Runtime values live in
    | AuthenticationSettings (Spatie Settings); the fluent
    | FilamentPanelBasePlugin::withAuthentication() API takes precedence.
    |
    */
    'auth' => [
        /*
        | Blade layout that Livewire auth views @extend. Hosts override this
        | to fit their public site chrome. Set to null to use a minimal
        | bundled fallback layout.
        */
        'layout' => 'layouts.app',

        /*
        | Front-of-site route registration. Set `enabled` to false to skip
        | route registration entirely (hosts wire their own routes), or
        | customise the prefix/name.
        */
        'routes' => [
            'enabled' => true,
            'prefix' => '',
            'name' => '',
            'middleware' => ['web'],
        ],

        /*
        | OTP service.
        */
        'otp' => [
            'code_length' => 6,
            'ttl_minutes' => 10,
            'max_attempts' => 5,
        ],

        /*
        | Throttle limits for auth endpoints. Used by ThrottleAuth middleware.
        | AuthenticationSettings overrides these per-deployment.
        */
        'throttle' => [
            'per_minute' => 5,
            'per_day' => 50,
        ],

        /*
        | OTP driver credentials. Static, environment-driven (never DB-stored).
        */
        'drivers' => [
            'whatsapp' => [
                'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v21.0'),
                'phone_id' => env('WHATSAPP_BUSINESS_PHONE_ID'),
                'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
                'template_name' => env('WHATSAPP_TEMPLATE_NAME', 'verification_code'),
                'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
            ],
            'twilio' => [
                'sid' => env('TWILIO_ACCOUNT_SID'),
                'token' => env('TWILIO_AUTH_TOKEN'),
                'from' => env('TWILIO_FROM'),
            ],
            'vonage' => [
                'key' => env('VONAGE_KEY'),
                'secret' => env('VONAGE_SECRET'),
                'sms_from' => env('VONAGE_SMS_FROM', 'Codenzia'),
            ],
            'email' => [
                'from_address' => env('MAIL_FROM_ADDRESS'),
                'from_name' => env('MAIL_FROM_NAME'),
            ],
        ],
    ],
];
