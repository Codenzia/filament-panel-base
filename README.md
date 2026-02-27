# Filament Panel Base

Multi-panel architecture support for [Filament v4](https://filamentphp.com) with shared branding, dynamic theme colors, CSS custom property injection, localization middleware, user moderation, and country/currency components.

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

### 5. Frontend Theme (Optional)

The package includes a built-in theme system with 17 color presets and runtime CSS variable injection. This enables Tailwind utility classes like `bg-brand-500` that update dynamically when the theme changes — no rebuild required.

**Step 1: Add components to your layout `<head>`:**

```blade
<x-panel-base::dark-mode-script />
<x-panel-base::theme-styles />
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

`<x-panel-base::dark-mode-script />` prevents a flash of unstyled content by applying the dark class before first paint. `<x-panel-base::theme-styles />` injects CSS custom properties (`--site-brand-*`, `--site-primary`, etc.) into `:root`.

**Step 2: Import the theme CSS in your `resources/css/app.css`:**

```css
@import "../../vendor/codenzia/filament-panel-base/resources/css/theme.css";
@import "tailwindcss";
```

This maps `--color-brand-*` to the runtime CSS variables via Tailwind v4's `@theme` directive, giving you utility classes like `bg-brand-500`, `text-brand-600`, etc.

Or publish the theme CSS for customization:

```bash
php artisan vendor:publish --tag=filament-panel-base-theme
```

**Step 3: Implement `ProvidesThemeColors` on your settings class (optional):**

```php
use Codenzia\FilamentPanelBase\Contracts\ProvidesThemeColors;
use Codenzia\FilamentPanelBase\Support\ThemePresets;

class GeneralSettings extends Settings implements ProvidesThemeColors
{
    public string $theme_preset = 'ocean_blue';
    public string $primary_color = '#3b82f6';
    // ... other color properties

    public function getThemeColors(): array
    {
        if ($this->theme_preset !== 'custom') {
            $preset = ThemePresets::get($this->theme_preset);
            if ($preset) {
                unset($preset['label']);
                return $preset;
            }
        }

        return [
            'primary_color' => $this->primary_color,
            // ... map all 15 color keys
        ];
    }
}
```

When no settings class implements `ProvidesThemeColors`, the package falls back to `config('filament-panel-base.theme.preset')` (default: `ocean_blue`).

## Features

### BasePanelProvider

Abstract panel provider that applies shared configuration to all panels:

- **Brand name** — resolved from settings class or `config('app.name')`
- **Logo & favicon** — resolved from settings via `getAppLogoUrl()` / `getAppFaviconUrl()`
- **Dynamic colors** — reads hex colors from settings (or `ProvidesThemeColors` contract) and converts via `Color::hex()`
- **User menu** — profile slideOver, role display, phone, email, cross-panel navigation
- **Panel badge** — "Administration" / "My Account" badge after the logo
- **Visit Website** button in the topbar
- **Shared middleware stack** — session, CSRF, Filament essentials

#### Panel configuration API

Call these methods on your `BasePanelProvider` subclass **before** `configureSharedSettings()`:

```php
class AdminPanelProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $this
            ->addTitleBadge('Administration', 'heroicon-o-shield-check', 'primary', showOnAuthForm: true)
            ->showVisitWebsite(label: 'Back to site')
            ->showLanguageDropdown()
            ->sidebarCollapseButtonPosition('right')
            ->sidebarIcon('heroicon-o-bars-3')
            ->sidebarSlideover();

        $this->configureSharedSettings(
            $panel->default()->id('admin')->path('admin')->login()
        );

        return $panel->authMiddleware([Authenticate::class]);
    }
}
```

**Topbar**

| Method | Default | Description |
|---|---|---|
| `showLanguageDropdown(bool $show = true)` | `true` | Show or hide the locale switcher dropdown in the topbar. |
| `showVisitWebsite(bool $show = true, ?string $label = null)` | `true` | Show or hide the "Visit Website" link button. Pass `$label` to override the translated default. |
| `addTitleBadge(string $label, ?string $icon = null, string $color = 'primary', bool $showOnAuthForm = true)` | — | Render a small colour-coded badge next to the logo. Accepts `'primary'`, `'success'`, `'warning'`, `'danger'`, `'info'`, or `'gray'`. When `$showOnAuthForm` is `true` (default), the badge is also shown centred above the login and register forms. |

**Sidebar**

| Method | Default | Description |
|---|---|---|
| `sidebarCollapseButtonPosition(string $position)` | `'left'` | `'left'` keeps Filament's default topbar button. `'right'` replaces it with a pill button on the right edge of the sidebar. |
| `sidebarIcon(string $icon)` | — | Replace the default chevron with any Filament icon string (e.g. `'heroicon-o-bars-3'`). Applies to both left and right button positions. |
| `sidebarSlideover(bool $enabled = true)` | `true` | When enabled, the sidebar overlays the main content on desktop instead of pushing it. A dim backdrop is shown, matching Filament's mobile drawer behaviour. Call `->sidebarSlideover(false)` to restore the default push layout. |

> **Note:** Slideover mode is **on by default**. When it is active and no custom icon is set, the left-position button automatically uses `heroicon-o-bars-3` (the mobile drawer icon) to signal drawer behaviour. The right-position pill button always uses the chevron SVG by default.

### Theme System

The package ships 17 predefined color presets plus a `custom` option. Each preset defines 15 color keys covering primary, secondary, background, surface, text, status, border, and shadow colors.

**Available presets:** Ocean Blue, Forest Green, Sunset Orange, Royal Purple, Rose Garden, Modern Dark, Teal Breeze, Amber Gold, Slate Steel, Crimson Fire, Sky Light, Emerald Fresh, Indigo Classic, Pink Blossom, Warm Earth, Midnight Blue, Charcoal Noir.

**ThemePresets API:**

```php
use Codenzia\FilamentPanelBase\Support\ThemePresets;

ThemePresets::all();         // All 18 presets (17 + custom)
ThemePresets::labels();      // ['ocean_blue' => 'Ocean Blue', ...] — for Select dropdowns
ThemePresets::get('ocean_blue'); // Single preset array or null
ThemePresets::defaults();    // Ocean Blue colors (the default)
ThemePresets::colorKeys();   // ['primary_color', 'danger_color', ...] — all 15 keys
```

**Blade components:**

| Component | Purpose |
|---|---|
| `<x-panel-base::theme-styles />` | Injects CSS custom properties into `:root` using `color-mix()` for brand scale generation |
| `<x-panel-base::dark-mode-script />` | FOUC prevention — applies `dark` class before first paint |

The `theme-styles` component accepts an optional `:colors` prop. When omitted, it resolves colors automatically via `FilamentPanelBasePlugin::make()->getThemeColors()`.

**Color resolution order:**

1. Settings class implementing `ProvidesThemeColors` interface
2. Config preset (`filament-panel-base.theme.preset`) + color overrides
3. Ocean Blue defaults

**CSS variables injected by `<x-panel-base::theme-styles />`:**

| Variable | Source |
|---|---|
| `--site-primary` | Primary brand color |
| `--site-primary-hover` | Primary hover state |
| `--site-brand-50` to `--site-brand-900` | Generated via `color-mix()` from primary |
| `--site-secondary`, `--site-background`, `--site-surface` | Semantic colors |
| `--site-text-primary`, `--site-text-secondary`, `--site-text-on-primary` | Text colors |
| `--site-success`, `--site-warning`, `--site-danger`, `--site-info` | Status colors |
| `--site-border`, `--site-shadow` | UI element colors |

### Middleware

| Middleware | Description |
|---|---|
| `SetLocale` | Detects locale from session/cookie, validates against `ProvidesLocales` provider |
| `SetCountry` | Auto-detects country from IP using geo API, stores in session |
| `SetCurrency` | Sets active currency from country relationship or session |
| `EnsureUserApproved` | Blocks suspended/pending users (requires `HasModerationStatus` contract) |

### Registration Settings

The package provides a shared `RegistrationSettings` class (`spatie/laravel-settings`) with two fields that complement the `EnsureUserApproved` middleware and `HasModerationStatus` contract:

- `registration_mode` — `'open'` (default) or `'moderated'`
- `require_email_verification` — `true` (default) or `false`

The settings migration is auto-registered and idempotent. Run `php artisan migrate` to create the entries.

**Using the settings in your registration flow:**

```php
use Codenzia\FilamentPanelBase\Settings\RegistrationSettings;

$settings = app(RegistrationSettings::class);

$user = User::create([
    'name' => $name,
    'email' => $email,
    'password' => $password,
    'status' => $settings->registration_mode === 'moderated' ? 'pending' : 'approved',
]);

if ($settings->require_email_verification) {
    // redirect to email verification
}
```

The package does **not** ship an admin page for these settings — consuming projects should build their own UI tailored to their needs.

### Contracts

Implement these interfaces on your models/settings to integrate with the package:

```php
use Codenzia\FilamentPanelBase\Contracts\ProvidesThemeColors;

class GeneralSettings extends Settings implements ProvidesThemeColors
{
    public function getThemeColors(): array
    {
        // Return array with keys like 'primary_color', 'danger_color', etc.
    }
}
```

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
| `HasProfileSlideOver` | Profile-editing slideOver action in the user menu with vertical tabs (Personal Info + Change Password) |
| `NotifiesAdmins` | Sends notifications to admin-role users and optionally the content author |
| `HasContactValidation` | Shared validation rules for lead capture forms (name, phone, email, WhatsApp) |

#### HasProfileSlideOver

Used by `BasePanelProvider` to add a profile-editing slideOver to the Filament user menu. Includes name, email, and password fields out of the box. Override methods in your panel provider to add project-specific fields:

| Method | Purpose |
|---|---|
| `getProfilePersonalInfoComponents()` | Form fields for the "Personal Information" tab |
| `getProfilePasswordComponents()` | Form fields for the "Change Password" tab |
| `getProfileFormTabs()` | Customise tabs (add new ones, reorder, etc.) |
| `getProfileFormData()` | Data to fill the form (override to include relationships) |
| `saveProfileData(array $data)` | Persist form data (override to handle media sync, etc.) |

**Example — adding an avatar and phone field:**

```php
use Codenzia\FilamentMedia\Forms\MediaPickerField;
use Codenzia\FilamentPanelBase\Forms\Components\PhoneInput;

class UserPanelProvider extends BasePanelProvider
{
    protected function getProfileFormData(): array
    {
        $data = parent::getProfileFormData();
        $data['media_avatar'] = filament()->auth()->user()->images()->first()?->getKey();

        return $data;
    }

    protected function getProfilePersonalInfoComponents(): array
    {
        return [
            ...parent::getProfilePersonalInfoComponents(),
            PhoneInput::make('phone')->label(__('Phone'))->countries(/* ... */),
            MediaPickerField::make('media_avatar')->label(__('Avatar'))->imageOnly(),
        ];
    }

    protected function saveProfileData(array $data): void
    {
        $user = filament()->auth()->user();

        if (array_key_exists('media_avatar', $data)) {
            $user->syncMediaByIds($data['media_avatar'] ? [$data['media_avatar']] : []);
            unset($data['media_avatar']);
        }

        parent::saveProfileData($data);
    }
}
```

### Blade Components

**Form fields** (Livewire-bound via `@entangle`):

```blade
<x-panel-base::country-select :countries="$countries" wire-model="country_id" />
<x-panel-base::country-code-select :countries="$countries" wire-model="country_code" />
<x-panel-base::phone-input :countries="$countries" country-code-model="country_code" phone-model="whatsapp" />
```

`phone-input` combines a country code dropdown and phone number input into a single bordered group with a searchable dropdown. It accepts the same country collection as the other components and binds to two separate Livewire properties.

| Prop | Default | Description |
|---|---|---|
| `:countries` | *(required)* | Eloquent collection of Country models (must have `code`, `phone_code`, `name`) |
| `country-code-model` | `'country_code'` | Livewire property for the selected dial code |
| `phone-model` | `'whatsapp'` | Livewire property for the phone number |
| `:default` | `null` | Fallback country code (e.g. `'+962'`) |
| `placeholder` | `'501234567'` | Input placeholder |

**Navbar switchers** (pair with `SetCountry`/`SetCurrency`/`SetLocale` middleware):

```blade
<x-panel-base::country-switcher :mode="$countryDropdownMode" />
<x-panel-base::currency-switcher />
<x-panel-base::locale-switcher :locales="$locales" :currentLocale="$currentLocale" />
<x-panel-base::dark-mode-toggle />
```

The switchers read view-shared data from middleware (`$availableCountries`, `$currentCountry`, `$availableCurrencies`, `$currentCurrency`, `$currentCurrencyMode`) and require routes named `country.switch`, `currency.switch`, and `locale.switch` in the consuming app.

### Filament Form Components

Reusable form fields for Filament v4 panels. Both use Filament's native CSS classes (`fi-input-wrp`, `fi-input`) for full theme compatibility — any custom panel styling automatically applies.

#### CountrySelect

A Select field with flag icons beside each country name. Extends `Filament\Forms\Components\Select` with `allowHtml()`, `searchable()`, and `preload()` pre-configured.

**From relationship** (stores the country ID):

```php
use Codenzia\FilamentPanelBase\Forms\Components\CountrySelect;

CountrySelect::make('country_id')
    ->relationship('country', 'name')
    ->required()
    ->live()
```

The related model must have a `code` column with the ISO country code (e.g. `jo`, `sa`). To use a different column:

```php
CountrySelect::make('country_id')
    ->codeAttribute('iso_code')
    ->relationship('country', 'name')
```

**From array** (keys are ISO codes, stored as value):

```php
CountrySelect::make('country')
    ->countries(['jo' => 'Jordan', 'sa' => 'Saudi Arabia'])
```

**From array** (keys are IDs, with explicit code):

```php
CountrySelect::make('country_id')
    ->countries([
        1 => ['name' => 'Jordan', 'code' => 'jo'],
        2 => ['name' => 'Saudi Arabia', 'code' => 'sa'],
    ])
```

**From closure** (lazy-loaded):

```php
CountrySelect::make('country_id')
    ->countries(fn () => Country::published()
        ->get()
        ->mapWithKeys(fn ($c) => [$c->id => ['name' => $c->name, 'code' => strtolower($c->code)]])
        ->toArray())
```

#### PhoneInput

A compound field with a country code dropdown (flags + dial code) and a phone number input. Stores the combined value as a single string (e.g. `+962501234567`). Uses Filament's `fi-input-wrp` wrapper with a non-inline prefix for the country code section.

```php
use Codenzia\FilamentPanelBase\Forms\Components\PhoneInput;

PhoneInput::make('phone')
    ->label(__('Phone'))
    ->countries(fn (): array => Country::published()
        ->whereNotNull('phone_code')
        ->orderBy('order')
        ->get()
        ->map(fn (Country $c): array => [
            'code' => strtolower($c->code),
            'phone_code' => $c->phone_code,
            'name' => $c->name,
        ])
        ->toArray())
```

Each country in the array must have `code` (ISO, lowercase), `phone_code` (e.g. `+962`), and `name`.

**Default country code:**

```php
PhoneInput::make('phone')
    ->countries($countries)
    ->defaultCountryCode('+962')
```

**Placeholder & validation:**

```php
PhoneInput::make('phone')
    ->countries($countries)
    ->placeholder('7XXXXXXXX')
    ->required()
    ->unique(ignoreRecord: true)
```

The field supports `disabled()`, `readOnly()`, `live()`, and standard Filament validation rules.

### Flag Icons

This package bundles [flag-icons](https://github.com/lipis/flag-icons) CSS and SVGs for country flag display. On Filament panels, the CSS is auto-injected via `@filamentStyles`. For frontend layouts, add a `<link>` tag:

```blade
<link rel="stylesheet" href="{{ asset('css/codenzia/filament-panel-base/flag-icons.css') }}">
```

Publish the SVG assets:

```bash
php artisan filament:assets
php artisan vendor:publish --tag=filament-panel-base-assets
```

**Note:** The bundled `flag-icons.css` is minified with very long lines. If your IDE's spell checker warns about it (e.g. cSpell's "line length greater than 20000"), add it to your ignore list in `.vscode/settings.json`:

```json
{ "cSpell.ignorePaths": ["**/flag-icons.css"] }
```

**Important:** The CSS class prefix is `flag` (not the upstream `fi`) to avoid collision with Filament's own `.fi-*` class namespace. Usage:

```html
<span class="flag flag-sa"></span>   <!-- Saudi Arabia (4:3) -->
<span class="flag flags flag-gb"></span> <!-- UK (1:1 square) -->
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

    'theme' => [
        'preset' => 'ocean_blue',  // any ThemePresets key
        'colors' => [],            // override individual color keys
    ],
];
```

## Translatable Content (Optional)

The package provides automatic integration with [spatie/laravel-translatable](https://github.com/spatie/laravel-translatable) and [lara-zeus/spatie-translatable](https://github.com/lara-zeus/spatie-translatable) for Filament v4. When both packages are installed, `BasePanelProvider` auto-registers the `SpatieTranslatablePlugin` on every panel — no manual plugin registration needed.

### When to use this

Use this integration when your project has **translatable database content** (e.g. product names, category descriptions, page content stored in multiple languages). This is different from Laravel's built-in `__()` / `trans()` localization which translates static UI strings.

**Good fit:** A real estate site where property names, descriptions, and locations are stored in both English and Arabic.

**Not needed:** A single-language app, or an app that only translates UI labels via language files.

### Step 1: Install the packages

```bash
composer require spatie/laravel-translatable lara-zeus/spatie-translatable
```

### Step 2: Configure available locales

In your `config/filament-panel-base.php`, set the locales your content supports:

```php
'locale' => [
    'provider' => \App\Models\Language::class,
    'available' => ['en', 'ar'],  // ← used by the translatable plugin
    'detection_order' => ['session', 'cookie', 'config'],
],
```

The `locale.available` array is passed to `SpatieTranslatablePlugin::defaultLocales()` automatically. That's all the panel-level setup required — no need to register the plugin yourself.

### Step 3: Make your models translatable

Add the `HasTranslations` trait and declare which columns are translatable:

```php
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];
}
```

Translatable columns must be `json` type in the database. Create a migration if converting existing columns:

```php
Schema::table('categories', function (Blueprint $table) {
    $table->json('name')->change();
    $table->json('description')->nullable()->change();
});
```

### Step 4: Make your Filament resources translatable

Add the `Translatable` concern to each resource class:

```php
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CategoryResource extends Resource
{
    use Translatable;

    protected static ?string $model = Category::class;
    // ...
}
```

### Step 5: Add the locale switcher to resource pages

Add the `Translatable` concern and `LocaleSwitcher` action to each resource page:

**For ManageRecords pages:**

```php
use LaraZeus\SpatieTranslatable\Resources\Pages\ManageRecords\Concerns\Translatable;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class ManageCategories extends ManageRecords
{
    use Translatable;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            Actions\CreateAction::make()->slideOver(),
        ];
    }
}
```

**For ListRecords pages:**

```php
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class ListCategories extends ListRecords
{
    use Translatable;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
```

### Customising the integration

Override `registerTranslatablePlugin()` in your panel provider to customise the behaviour:

```php
class AdminPanelProvider extends BasePanelProvider
{
    protected function registerTranslatablePlugin(Panel $panel): void
    {
        // Custom locales per panel
        $panel->plugin(
            \LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin::make()
                ->defaultLocales(['en', 'ar', 'fr'])
                ->persist()
        );
    }
}
```

To disable the integration for a specific panel, override with an empty method:

```php
protected function registerTranslatablePlugin(Panel $panel): void
{
    // This panel does not need translatable content
}
```

## Plugin API

```php
FilamentPanelBasePlugin::make()
    // Resolve settings via closure
    ->settingsUsing(fn () => app(GeneralSettings::class))
    // Or by class name
    ->settingsClass(GeneralSettings::class)

// Get resolved theme colors (used internally by <x-panel-base::theme-styles />)
FilamentPanelBasePlugin::make()->getThemeColors();
// Returns: ['primary_color' => '#3b82f6', 'danger_color' => '#ef4444', ...]
```

## Requirements

- PHP 8.3+
- Laravel 12+
- Filament v4
- `spatie/laravel-settings` ^3.0 (required, for `RegistrationSettings`)
- `spatie/laravel-permission` (optional, for `NotifiesAdmins` trait)
- `spatie/laravel-translatable` + `lara-zeus/spatie-translatable` (optional, for translatable content)

## License

MIT
