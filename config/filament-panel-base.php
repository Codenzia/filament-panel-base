<?php

use App\Models\User;
use Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage;

return [
    /*
    |--------------------------------------------------------------------------
    | Admin navigation group
    |--------------------------------------------------------------------------
    |
    | Sidebar group the package's admin pages (Analytics, Authentication, Demo
    | settings) live under. Host apps can override this to place them wherever
    | fits their information architecture.
    |
    */
    'admin_navigation_group' => env('FILAMENT_PANEL_BASE_ADMIN_NAV_GROUP', 'System'),

    /*
    |--------------------------------------------------------------------------
    | Allowed sign-up email domains (config fallback)
    |--------------------------------------------------------------------------
    |
    | Restrict self-registration to these email domains (e.g. ['acme.com']).
    | Empty = any domain allowed. This is the fallback used before settings are
    | migrated / when the DB is unavailable; the admin Authentication settings
    | page (auth.allowed_email_domains) takes precedence at runtime. Set via
    | a comma-separated env, e.g. PANEL_ALLOWED_EMAIL_DOMAINS="acme.com,acme.io".
    |
    */
    'allowed_email_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PANEL_ALLOWED_EMAIL_DOMAINS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | "Powered by Codenzia" footer rendered on every Filament panel page.
    | Set CODENZIA_BRANDING=false in .env to hide it — useful when an app
    | is sold/licensed to a customer who wants their own branding.
    |
    */
    'branding' => [
        'powered_by_enabled' => env('CODENZIA_BRANDING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Expiry (419) Handling
    |--------------------------------------------------------------------------
    |
    | When a session/CSRF token expires, Laravel returns HTTP 419 — showing a
    | bare "Page Expired" error on full-page submits and Livewire's "This page
    | has expired" modal on AJAX requests. With this enabled (default), the
    | package instead redirects the user cleanly to the login screen: a backend
    | handler catches TokenMismatchException on full-page requests, and an
    | injected Livewire hook intercepts 419 AJAX responses on every panel.
    |
    | Set PANEL_SESSION_EXPIRY=false to opt out (e.g. apps that handle 419
    | themselves).
    |
    | 'redirect_to' overrides the destination. null = smart default:
    |   current Filament panel login URL → route('login') → '/'.
    |
    */
    'session_expiry' => [
        'enabled' => env('PANEL_SESSION_EXPIRY', true),
        'redirect_to' => null,
    ],

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
    'user_model' => User::class,

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

        /*
        | Locale-switcher route registration. When enabled (default), the
        | package ships a `locale.switch` named route that sessions the
        | chosen locale and bounces back to the previous page. Disable if
        | the host app wires its own implementation under that name.
        */
        'routes' => [
            'enabled' => true,
            'prefix' => '',
            'middleware' => ['web'],
        ],
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
        // Allowlist of hosts the geo API may resolve to. The client IP is only
        // sent to one of these. Set to an empty array to disable the check.
        'geo_api_hosts' => ['ipapi.co'],
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
    | App-wide default palette applied to every panel that extends
    | BasePanelProvider. Values are hex color codes.
    |
    | Colors resolve by layered precedence (low → high), so a panel is never
    | Filament's scaffold amber:
    |   1. Neutral fallback (blue) baked into BasePanelProvider::getDefaultColors()
    |   2. These config values (this is the app-wide brand default)
    |   3. A settings class implementing ProvidesThemeColors (admin-editable, live)
    |   4. Per-panel ->primaryColor() / ->brandColors() in the panel provider —
    |      an explicit code pin that wins over the settings model
    |
    | Set the app brand here once for single-panel apps; use ->primaryColor()
    | in the provider when panels need different colors (e.g. admin vs storefront).
    | Omit ->primaryColor() to let a ProvidesThemeColors settings class theme the
    | panel live from the admin UI.
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
    | User Management (opt-in)
    |--------------------------------------------------------------------------
    |
    | Defaults for the shared Users resource enabled via
    | FilamentPanelBasePlugin::make()->withUserManagement(). The fluent API
    | arguments take precedence over these at runtime. `model` null falls back to
    | the app's configured auth user model.
    |
    */
    'user_management' => [
        'navigation_group' => 'User Management',
        'navigation_sort' => 10,
        'navigation_icon' => 'heroicon-o-users',
        'model' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance settings page (opt-in)
    |--------------------------------------------------------------------------
    |
    | Defaults for the shared branding page enabled via
    | FilamentPanelBasePlugin::make()->withAppearanceSettings(). It edits the
    | panel's own settings instance (->settingsUsing()/->settingsClass()).
    |
    */
    'appearance' => [
        'navigation_group' => 'Settings',
        'navigation_sort' => 90,
        'navigation_icon' => 'heroicon-o-paint-brush',
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
    /*
    |--------------------------------------------------------------------------
    | Demo Page
    |--------------------------------------------------------------------------
    |
    | Generic /demo landing page used for sales demos and QA — auto-discovered
    | model counts, one-click "login as", optional Standard/Demo seed buttons
    | (rendered only when the seeder class exists), and a footer with build
    | date and PHP/Laravel/Filament versions.
    |
    | Enable with FILAMENT_PANEL_BASE_DEMO_ENABLED=true and set the gate
    | password via APP_DEMO_PAGE_PWD. Disabled by default — never registers
    | the route unless explicitly opted in.
    |
    */
    'demo' => [
        'enabled' => env('FILAMENT_PANEL_BASE_DEMO_ENABLED', false),

        /* The route URI where the demo page lives. */
        'route' => env('FILAMENT_PANEL_BASE_DEMO_ROUTE', '/demo'),

        /* Middleware applied to the demo route. */
        'middleware' => ['web'],

        /* Layout the demo page extends. The bundled standalone layout is
        | self-contained (Tailwind CDN) so the page renders regardless of
        | the host app's CSS build state. */
        'layout' => 'filament-panel-base::layouts.demo',

        /* env() key holding the demo page password. The same value gates
        | both opening the page and confirming seed buttons. */
        'password_env' => 'APP_DEMO_PAGE_PWD',

        /* When true, /demo auto-unlocks if no password is configured
        | (preserves the "don't brick a fresh install" convenience). When
        | false (default), the gate stays locked when the password is empty
        | — /demo is never public unless a password is explicitly set. */
        'allow_empty_password' => env('FILAMENT_PANEL_BASE_DEMO_ALLOW_EMPTY', false),

        /* The default password presented under the "Click Login to switch
        | to any user" banner — purely cosmetic; doesn't unlock anything. */
        'shared_user_password' => env('FILAMENT_PANEL_BASE_DEMO_USER_PWD', 'password'),

        /* Where "Login as" sends users after authenticating. */
        'app_url' => env('FILAMENT_PANEL_BASE_DEMO_APP_URL', '/admin'),

        /* Optional admin email re-logged-in after seeding. Falls back to the
        | first user (orderBy id) if empty or not found. */
        'admin_email' => env('FILAMENT_PANEL_BASE_DEMO_ADMIN_EMAIL', ''),

        /* Seeder classes. Only renders the corresponding button if the
        | class exists in the host application. */
        'seeders' => [
            'standard' => 'Database\\Seeders\\StandardSeeder',
            'demo' => 'Database\\Seeders\\DemoSeeder',
        ],

        /* Explicit stat tiles. Leave empty to auto-discover all Eloquent
        | models under app/Models. Each entry: ['model' => FQCN, 'label'
        | => ..., 'icon' => 'heroicon-o-...']. */
        'stats' => [],

        /* Model FQCNs or basenames to exclude from auto-discovery. */
        'exclude_models' => [],

        /* The Livewire component class that backs the /demo route. Hosts can
        | subclass the default to override data-collection methods
        | (collectStats, collectUsers, canLogInAs, ...) without forking the
        | package. Set to the host's subclass FQCN to swap. */
        'component' => DemoPage::class,

        /* Named Livewire component slots rendered inside the demo page chrome.
        | Each value is a Livewire component FQCN (or null to skip). The host's
        | component receives no extra parameters by default; subscribe to the
        | parent's events or read from session/config as needed. */
        'sections' => [
            'before_stats' => null,
            'after_stats' => null,
            'before_users' => null,
            'after_users' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Module
    |--------------------------------------------------------------------------
    |
    | Static defaults for the Analytics module. Runtime values live in
    | AnalyticsSettings (Spatie Settings); the fluent
    | FilamentPanelBasePlugin::withAnalytics() API takes precedence.
    |
    | `exclude_routes` is the only knob NOT mirrored in AnalyticsSettings —
    | it's deploy-time config (asset / health-check paths) that has no UX
    | value behind an admin Settings page.
    |
    */
    'analytics' => [
        /*
        | Route patterns (Laravel `Request::is()` globs) that should never
        | produce a `visits` row. Cosmetic + perf — admins still see real
        | traffic in widgets, not asset noise.
        */
        'exclude_routes' => [
            'livewire/livewire.js',
            'livewire/livewire.js.map',
            'horizon*',
            'telescope*',
            'sanctum/*',
            '_debugbar/*',
            'up',
        ],
    ],

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
        | OAuth (Socialite) login behaviour.
        |
        | `remember` controls whether a successful social sign-in issues a
        | long-lived "remember me" cookie. Defaults to false so social login
        | matches the (opt-in) password path rather than silently issuing a
        | persistent cookie on every flow.
        */
        'oauth' => [
            'remember' => false,
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
        | Throttle limits for auth flows. AuthenticationSettings overrides
        | these per-deployment. Both windows are consumed by two layers:
        |
        |   - The ThrottlesAuthAttempts trait used by the Livewire auth
        |     components (Login, Register, ForgotPassword, ResetPassword,
        |     VerifyOtp, VerifyEmailNotice) — keyed on IP + identifier.
        |   - The ThrottleAuth middleware on the OAuth redirect/callback
        |     routes — keyed on IP only.
        |
        | Defaults match Laravel Fortify's login rate-limiter (5/min).
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
