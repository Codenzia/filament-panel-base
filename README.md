# Filament Panel Base

Multi-panel architecture support for [Filament v4](https://filamentphp.com) with shared branding, dynamic colors, localization middleware, user moderation, and country/currency components.

## Installation

```bash
composer require codenzia/filament-panel-base
```

Publish the config file:

```bash
php artisan vendor:publish --tag="filament-panel-base-config"
```

## Setup

### 1. Register the plugin

```php
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentPanelBasePlugin::make()
                ->settingsUsing(fn () => app(\App\Settings\GeneralSettings::class)),
        ]);
}
```

### 2. Extend BasePanelProvider

```php
use Codenzia\FilamentPanelBase\Providers\BasePanelProvider;

class AdminPanelProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $this->configureSharedSettings(
            $panel->default()->id('admin')->path('admin')->login()
        );

        return $panel
            ->middleware($this->getSharedMiddleware())
            ->authMiddleware([Authenticate::class]);
    }
}
```

### 3. Register middleware

In `bootstrap/app.php`:

```php
use Codenzia\FilamentPanelBase\Middleware\SetCountry;
use Codenzia\FilamentPanelBase\Middleware\SetCurrency;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->web([
        SetCountry::class,
        SetCurrency::class,
    ]);
})
```

### 4. Custom Theme (Tailwind v4)

If your panel uses a custom Filament theme with `->viteTheme()`, you must add `@source` directives so Tailwind compiles the classes used by this package (panel badge, visit website button, blade components, etc.):

```css
/* resources/css/filament/admin/theme.css */
@source '../../../../vendor/codenzia/filament-panel-base/src/**/*.php';
@source '../../../../vendor/codenzia/filament-panel-base/resources/views/**/*.blade.php';
```

Then rebuild your assets with `npm run build`.

## Features

### BasePanelProvider

Abstract panel provider that applies shared configuration to all panels:

- **Brand name** — resolved from settings class or `config('app.name')`
- **Logo & favicon** — resolved from settings via `getAppLogoUrl()` / `getAppFaviconUrl()`
- **Dynamic colors** — reads hex colors from settings and converts via `ColorUtils`
- **User menu** — profile link, role display, phone, email, cross-panel navigation
- **Panel badge** — "Administration" / "My Account" badge after the logo
- **Visit Website** button in the topbar
- **Shared middleware stack** — session, CSRF, Filament essentials

### Middleware

| Middleware | Description |
|---|---|
| `SetLocale` | Detects locale from session/cookie, validates against `ProvidesLocales` provider |
| `SetCountry` | Auto-detects country from IP using geo API, stores in session |
| `SetCurrency` | Sets active currency from country relationship or session |
| `EnsureUserApproved` | Blocks suspended/pending users (requires `HasModerationStatus` contract) |

### Contracts

Implement these interfaces on your models to integrate with the middleware:

```php
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;

class User extends Authenticatable implements HasModerationStatus
{
    public function isSuspended(): bool { /* ... */ }
    public function isPending(): bool { /* ... */ }
}
```

```php
use Codenzia\FilamentPanelBase\Contracts\ProvidesLocales;

class Language extends Model implements ProvidesLocales
{
    public static function getActive(): array { /* ... */ }
}
```

```php
use Codenzia\FilamentPanelBase\Contracts\ProvidesCountries;

class Country extends Model implements ProvidesCountries
{
    public function scopePublished(Builder $query): Builder { /* ... */ }
    public function currency() { /* ... */ }
}
```

```php
use Codenzia\FilamentPanelBase\Contracts\ProvidesCurrencies;

class Currency extends Model implements ProvidesCurrencies
{
    public function getCodeAttribute(): string { /* ... */ }
    public function getSymbolAttribute(): string { /* ... */ }
}
```

### Traits

| Trait | Description |
|---|---|
| `NotifiesAdmins` | Sends notifications to admin-role users and optionally the content author |
| `HasContactValidation` | Shared validation rules for lead capture forms (name, phone, email, WhatsApp) |

### Blade Components

```blade
<x-panel-base::country-select />
<x-panel-base::country-code-select />
```

### ColorUtils

Static utility class for color manipulation:

```php
use Codenzia\FilamentPanelBase\Support\ColorUtils;

ColorUtils::hexToRgb('#3b82f6');        // [59, 130, 246]
ColorUtils::hexToRgbString('#3b82f6');  // 'rgb(59, 130, 246)'
ColorUtils::hexToRgba('#3b82f6', 0.5);  // 'rgba(59, 130, 246, 0.5)'
ColorUtils::isLightColor('#ffffff');     // true
ColorUtils::getContrastColor('#3b82f6'); // '#ffffff'
```

## Configuration

```php
// config/filament-panel-base.php

return [
    'panels' => ['admin', 'dashboard'],
    'admin_role' => 'super_admin',
    'user_model' => \App\Models\User::class,

    'locale' => [
        'provider' => null,        // class implementing ProvidesLocales
        'available' => ['en'],
        'detection_order' => ['session', 'cookie', 'config'],
    ],

    'country' => [
        'auto_detect' => true,
        'default_id' => null,
        'model' => null,           // class implementing ProvidesCountries
        'geo_api' => 'https://ipapi.co/{ip}/json/',
        'cache_ttl' => 1440,
    ],

    'currency' => [
        'model' => null,           // class implementing ProvidesCurrencies
        'virtual_usd' => true,
    ],

    'contact_validation' => [
        'require_whatsapp' => false,
        'allow_email_alternative' => true,
    ],

    'settings_class' => null,      // class with branding properties

    'colors' => [
        'primary'   => '#3b82f6',
        'secondary' => '#6366f1',
        'danger'    => '#ef4444',
        'warning'   => '#f59e0b',
        'success'   => '#10b981',
        'info'      => '#06b6d4',
    ],
];
```

## Plugin API

```php
FilamentPanelBasePlugin::make()
    // Resolve settings via closure
    ->settingsUsing(fn () => app(GeneralSettings::class))
    // Or by class name
    ->settingsClass(GeneralSettings::class)
```

## Requirements

- PHP 8.3+
- Laravel 12+
- Filament v4
- `spatie/laravel-permission` (optional, for `NotifiesAdmins` trait)

## License

MIT
