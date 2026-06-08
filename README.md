# Filament Panel Base — Multi-panel branding, theming & localization for Filament

[![Latest Version](https://img.shields.io/packagist/v/codenzia/filament-panel-base.svg?style=flat-square)](https://packagist.org/packages/codenzia/filament-panel-base)
[![PHP Version](https://img.shields.io/packagist/php-v/codenzia/filament-panel-base.svg?style=flat-square)](https://packagist.org/packages/codenzia/filament-panel-base)
[![Filament](https://img.shields.io/badge/Filament-v4%20%7C%20v5-f59e0b?style=flat-square)](https://filamentphp.com)
[![License](https://img.shields.io/badge/license-MIT%20%7C%20Proprietary-blue?style=flat-square)](LICENSE.md)

**Multi-panel architecture support for [Filament v4 and v5](https://filamentphp.com)** — shared branding, dynamic theme colours, CSS custom-property injection, localisation middleware, user moderation hooks, and country/currency form components. The architectural foundation behind every Codenzia Filament app (aqarkom, LarafilPos, snapcar, LaraFilCommerce).

> **Why this exists.** Filament supports multiple panels out of the box, but each one defaults to its own brand, colour palette, locale handling, and middleware stack. Once you go beyond a single admin panel — say, an admin panel + a dashboard panel + a vendor panel — you end up duplicating provider code across three providers. This package extracts that shared layer into a `BasePanelProvider` you extend, plus a plugin that wires runtime branding from your `Settings` class.

> **Try it live:** A working integration is included in the [Codenzia plugins demo](https://github.com/Codenzia/plugins-demo) at `/admin/demo/panel-base`.

---

## Features

- **`BasePanelProvider`** — shared Filament panel scaffold; subclass per-panel for delta-only configuration.
- **Runtime branding** — pull logo, app name, primary colour from a `Settings` class (Spatie Settings).
- **Dynamic theme colours** — Filament `->colors([...])` reads from your settings at runtime.
- **CSS custom-property injection** — `--primary-500` etc. injected into the layout based on settings.
- **Localisation middleware** — locale switching, RTL detection, fallback chain.
- **User moderation** — block/unblock/login-as scaffolding for support workflows.
- **Country / currency components** — Filament form components with full ISO data and flag rendering.
- **Translation loader** — DB-backed translations via `spatie/laravel-translation-loader`.
- **`/demo` page** — drop-in Livewire landing page for sales demos and QA: password gate, auto-discovered model count tiles, one-click "login as" for every user, optional Standard/Demo seed buttons, footer with build date + dependency versions. Four levels of customization (config, view, section slots, full subclass).
- **Demo Settings admin page** — view/rotate/share the `/demo` password from the panel without touching `.env`. Singleton `demo_settings` table with encrypted password cast.
- **Analytics module** — visitor tracking middleware, auth-event recording, AnalyticsPage with 9 widgets (visitors today, 30-day chart, top pages, slowest pages, error-rate sparkline, geo breakdown, device types, auth funnel, failed-login chart), date-range filter, tenant scoping, hourly rollup + nightly prune commands.
- **Two-Factor Authentication module** — TOTP enrolment via the profile slide-over, post-login challenge flow with intermediate session state, 8 single-use recovery codes hashed at rest, remember-device cookie, optional role-based mandatory enrolment middleware. Pluggable issuer/digits/period/window via fluent API.
- **Sessions & Devices module** — self-service "Devices & Sessions" tab listing every active session from Laravel's database driver, per-row revoke, "sign out everywhere else", new-device-login event for sending alert emails.
- **Command Palette (Cmd-K)** — global Cmd-K modal augmenting Filament's chrome with navigation jumps, a "Recent" group auto-populated from record-page views, and an extensible registry where consumer plugins push their own actions.
- **Session-expiry (419) handling** — turns the jarring "Page Expired" error and Livewire "This page has expired" modal into a clean redirect to login, on by default for every panel (config kill-switch + optional front-of-site component).

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.3` |
| Filament | `^4.0 \|\| ^5.0` |
| `spatie/laravel-settings` | `^3.0` |
| `spatie/laravel-translation-loader` | `^2.8` |

---

## Installation

```bash
composer require codenzia/filament-panel-base
```

Publish the config file:

```bash
php artisan vendor:publish --tag="filament-panel-base-config"
```

### Admin navigation group

The package's admin pages — **Analytics**, **Authentication settings**, and **Demo settings** — are grouped in the sidebar under a single, configurable group (default **`System`**). Override it per app to fit your information architecture:

```php
// config/filament-panel-base.php
'admin_navigation_group' => 'System', // or 'Settings', 'Admin', etc.
```

```dotenv
# or via .env
FILAMENT_PANEL_BASE_ADMIN_NAV_GROUP="System"
```

Each page reads this at runtime via `getNavigationGroup()`, so a host app can place them wherever it likes without subclassing. (Demo settings sorts last within the group.)

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

### 4. Custom Theme (Tailwind v4) — Required

Filament v4's pre-built `dist/theme.css` only includes internal `fi-*` component styles. It does **not** include general Tailwind utility classes (`size-5`, `grid`, `rounded-xl`, `p-5`, etc.) needed by custom Blade views. You **must** create a Vite theme that imports Filament's source theme CSS and adds `@source` directives for your app and any packages with custom views.

**Step 1: Create the theme CSS file**

```css
/* resources/css/filament/admin/theme.css */
@import "../../../../vendor/filament/filament/resources/css/theme.css";

@source '../../../../resources/views/filament';
@source '../../../../app/Filament';
@source '../../../../app/Providers/Filament/**/*.php';
@source '../../../../vendor/codenzia/*/src/**/*.php';
@source '../../../../vendor/codenzia/*/resources/views/**/*.blade.php';
```

> **Important:** Import Filament's **source** `theme.css` (from `resources/css/`), not the pre-built `dist/theme.css`. The source file includes `@import 'tailwindcss' source(none)` plus all `fi-*` component styles. Using bare `@import 'tailwindcss'` instead will give you Tailwind utilities but no Filament component styles, breaking the panel layout.

The `@source` directives tell Tailwind v4 which files to scan for class names. Add additional `@source` lines for any other packages that ship custom Blade views.

**Step 2: Register the theme in your panel provider**

```php
$panel
    ->viteTheme('resources/css/filament/admin/theme.css');
```

**Step 3: Add the theme to your Vite config**

```js
// vite.config.js
laravel({
    input: [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/filament/admin/theme.css',
    ],
    refresh: true,
}),
```

**Step 4: Build**

```bash
npm run build
```

> **Without this setup**, utility classes used in package Blade views (icons, grids, spacing, etc.) will not be compiled and your panel will render incorrectly — for example, SVG icons may appear at full size instead of their intended dimensions.

### 5. Frontend Theme (Optional)

The package includes a built-in theme system with 17 color presets and runtime CSS variable injection. This enables Tailwind utility classes like `bg-brand-500` that update dynamically when the theme changes — no rebuild required.

**Step 1: Add components to your layout `<head>`:**

```blade
<x-filament-panel-base::dark-mode-script />
<x-filament-panel-base::theme-styles />
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

`<x-filament-panel-base::dark-mode-script />` prevents a flash of unstyled content by applying the dark class before first paint. `<x-filament-panel-base::theme-styles />` injects CSS custom properties (`--site-brand-*`, `--site-primary`, etc.) into `:root`.

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
            ->sidebarSlideover()
            ->sidebarSearchable();

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
| `sidebarCollapseToIcons(bool $enabled = true)` | `true` | When slideover is enabled, keep Filament's icon-only narrow bar when the sidebar is closed instead of sliding it fully off-screen. Users can still click nav icons without opening the full drawer. Call `->sidebarCollapseToIcons(false)` to slide the sidebar fully off-screen instead. |
| `sidebarSearchable(bool $enabled = true)` | `true` | Show a search input at the top of the sidebar navigation. Typing filters items client-side by matching labels; groups with no visible items are hidden automatically. The input is hidden when the sidebar is collapsed to icon-only mode. |

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
| `<x-filament-panel-base::theme-styles />` | Injects CSS custom properties into `:root` using `color-mix()` for brand scale generation |
| `<x-filament-panel-base::dark-mode-script />` | FOUC prevention — applies `dark` class before first paint |

The `theme-styles` component accepts an optional `:colors` prop. When omitted, it resolves colors automatically via `FilamentPanelBasePlugin::make()->getThemeColors()`.

**Color resolution order:**

1. Settings class implementing `ProvidesThemeColors` interface
2. Config preset (`filament-panel-base.theme.preset`) + color overrides
3. Ocean Blue defaults

**CSS variables injected by `<x-filament-panel-base::theme-styles />`:**

| Variable | Source |
|---|---|
| `--site-primary` | Primary brand color |
| `--site-primary-hover` | Primary hover state |
| `--site-brand-50` to `--site-brand-900` | Generated via `color-mix()` from primary |
| `--site-secondary`, `--site-background`, `--site-surface` | Semantic colors |
| `--site-text-primary`, `--site-text-secondary`, `--site-text-on-primary` | Text colors |
| `--site-success`, `--site-warning`, `--site-danger`, `--site-info` | Status colors |
| `--site-border`, `--site-shadow` | UI element colors |
| `--site-surface-page`, `--site-surface-page-dark` | Auth-layout body background (light / dark) |
| `--site-surface-card`, `--site-surface-card-dark` | Auth-card background (light / dark) |
| `--site-surface-input`, `--site-surface-input-dark` | Auth-input background (light / dark) |
| `--site-surface-border`, `--site-surface-border-dark` | Auth-card ring + input border (light / dark) |

### Theming auth pages

The package ships login / register / forgot-password / reset-password / verify-email / verify-OTP views (under `resources/views/livewire/auth`). They are **not** publishable — consuming projects don't override the Blade. Instead, recolor them with runtime CSS variables that `<x-filament-panel-base::theme-styles />` writes onto `:root`.

The auth views use two Tailwind v4 color scales backed by these variables:

- **`primary-{50..900}`** — buttons, focus rings, links. Mirrors `brand-*`; both point at `--site-primary` / `--site-primary-hover` and the `--site-brand-*` scale.
- **`surface-card` / `surface-input` / `surface-border`** (each with a `-dark` companion) — card chrome, input background, input/card border.

**Overridable `--site-*` knobs:**

| Variable | Default | Where it shows up |
|---|---|---|
| `--site-primary` | `#3b82f6` | Submit button, focus rings, links |
| `--site-primary-hover` | `#2563eb` | Button hover state |
| `--site-surface-page` | `#f9fafb` | Auth layout body background (light) |
| `--site-surface-page-dark` | `#111827` | Auth layout body background (dark) |
| `--site-surface-card` | `#ffffff` | Card background (light) |
| `--site-surface-card-dark` | `#1f2937` | Card background (dark) |
| `--site-surface-input` | `#ffffff` | Input background (light) |
| `--site-surface-input-dark` | `#111827` | Input background (dark) |
| `--site-surface-border` | `#d1d5db` | Input border + card ring (light) |
| `--site-surface-border-dark` | `#374151` | Input border + card ring (dark) |

**Example — recolor without touching Blade:**

```css
/* resources/css/app.css */
@import "../../vendor/codenzia/filament-panel-base/resources/css/theme.css";
@import "tailwindcss";

:root {
    --site-primary: #16a34a;          /* green-600 — buttons, focus rings */
    --site-primary-hover: #15803d;    /* green-700 */
    --site-surface-card: #f9fafb;     /* gray-50 — card panel */
    --site-surface-input: #ffffff;
    --site-surface-border: #e5e7eb;   /* gray-200 — softer hairline */
}
```

These overrides also apply if you set the matching keys (`primary_color`, `primary_hover_color`, `surface_page_color`, `surface_page_dark_color`, `surface_card_color`, `surface_card_dark_color`, `surface_input_color`, `surface_input_dark_color`, `surface_border_color`, `surface_border_dark_color`) on a settings class implementing `ProvidesThemeColors` — `<x-filament-panel-base::theme-styles />` writes them onto `:root` for you.

### Auth throttling

Brute-force protection for the Livewire auth pages lives **inside the components**, not on the route. Filament/Livewire form submissions POST to `/livewire/update`, which bypasses route-level middleware — so any `throttle` middleware on the auth pages would never see the credential submission. The package handles this via the `ThrottlesAuthAttempts` trait used by `Login`, `Register`, `ForgotPassword`, `ResetPassword`, `VerifyOtp`, and `VerifyEmailNotice::resend`.

Three buckets are checked on every attempt:

- **Per-IP, per-minute** — catches one-IP rapid-fire.
- **Per-identifier, per-minute** — catches distributed credential stuffing against one account from many IPs.
- **Per-IP, per-day** — long-window backstop; not cleared on successful login.

Both windows pull their limits from `AuthenticationSettings::throttle_per_minute` (default `5`) and `throttle_per_day` (default `50`). When a budget is exhausted, the component throws a `ValidationException` with the `auth.throttle_rate_limited` message routed to the form's error bag — no extra UI work needed.

Identifiers (emails, phones, user ids, OTP targets) are HMAC'd with the app key before being used as cache keys, so raw addresses never land in the cache store.

The `ThrottleAuth` middleware still ships, but it's scoped to the OAuth redirect/callback routes only (where every hit triggers external API work). Don't attach it to Livewire-backed routes — it has no effect there and only causes confusion.

**Apply the same pattern to a custom auth flow:**

```php
use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;

class CustomLogin extends \Livewire\Component
{
    use ThrottlesAuthAttempts;

    public function submit(): void
    {
        $this->validate([...]);

        $this->ensureNotRateLimited('custom-login', $this->identifier);

        if (! Auth::attempt(...)) {
            $this->hitRateLimiter('custom-login', $this->identifier);
            $this->addError('identifier', __('...'));

            return;
        }

        $this->clearRateLimiter('custom-login', $this->identifier);
        // ...
    }
}
```

### Social login (OAuth)

The Auth module ships a complete social-login flow built on `laravel/socialite`: redirect + callback routes, find-or-create-or-link logic, multi-identity storage in a `social_accounts` table, profile UI for linking/unlinking, and three email-conflict policies to defeat account-takeover.

**What the plugin gives you:**

- `GET /oauth/{provider}/redirect` and `GET /oauth/{provider}/callback` routes, throttled and gated by both `services.{provider}.client_id` and the admin's enable toggle.
- A `social_accounts` table (one row per linked identity per user) with encrypted access/refresh tokens.
- `FindsOrCreatesFromSocialite` trait that resolves a Socialite payload to a User by either matching `provider`+`provider_id`, applying the configured email-conflict policy, or creating a fresh user.
- Connect/disconnect profile UI (`<livewire:filament-panel-base::auth.manage-social-accounts />`).
- `SocialAccountMapping` (pre-persistence, mutable) and `SocialUserLinked` (post-persistence) events for app-level customisation.
- Inline brand-icon Blade component (`<x-filament-panel-base::social-provider-icon :provider="$p" />`) for the common providers — no external icon dependency.

**What the host app provides:**

- The `laravel/socialite` package and provider credentials.
- A User model that implements `SupportsSocialLogin` and uses the default trait.
- The `social_accounts` migration, published from the plugin.

#### Setup

1. **Install Socialite:**

    ```bash
    composer require laravel/socialite
    ```

2. **Publish the migrations and run them:**

    ```bash
    php artisan vendor:publish --tag=filament-panel-base-auth-migrations
    php artisan migrate
    ```

    This adds the `social_accounts` table. If you previously used the legacy single-provider columns (`users.provider` / `users.provider_id`), the publish also drops a one-shot data migration that copies them into the new table and removes the legacy columns. The migration is idempotent — safe on fresh installs.

3. **Make your User model social-aware:**

    ```php
    use Codenzia\FilamentPanelBase\Auth\Concerns\FindsOrCreatesFromSocialite;
    use Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin;

    class User extends Authenticatable implements SupportsSocialLogin
    {
        use FindsOrCreatesFromSocialite;
    }
    ```

    The trait provides `findOrCreateFromSocialite()`, `linkSocialAccount()`, and the `socialAccounts()` HasMany relation. Override any of them on the model if you need different behaviour.

4. **Configure provider credentials** in `config/services.php` — standard Socialite:

    ```php
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => '/oauth/google/callback',
    ],
    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect'      => '/oauth/github/callback',
    ],
    ```

    The callback paths above match the routes the plugin registers — set them verbatim in your OAuth app dashboards too.

5. **Enable providers via the plugin:**

    ```php
    use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;

    FilamentPanelBasePlugin::make()
        ->withAuthentication(fn ($auth) => $auth
            ->social(['google', 'github'])
            ->socialEmailLinking('require_login')   // safest default
            ->socialTrustVerifiedEmail(true)
        );
    ```

    Or flip them at runtime from the admin's auth settings page — fluent overrides win for the lifetime of the request, settings persist.

#### Email-conflict policies

When a user signs in via a provider whose email matches an existing local user that has *not* previously linked this provider, the plugin needs to decide what to do. Pick one with `->socialEmailLinking($policy)` or the `auth.social_email_linking` setting:

| Policy | Behaviour | Use when |
|---|---|---|
| `require_login` (default) | Refuse the auto-link. Redirect to login with a hint: "Sign in with your original method, then connect this provider from your profile." | Public-facing apps. **Recommended.** Defeats the account-takeover vector where an attacker spins up a provider account using a victim's email. |
| `trust_verified` | Link only when **both** sides assert verified email: the user has `email_verified_at` set **and** the provider's payload includes `email_verified: true`. | Mid-trust apps that want fewer support tickets without inviting takeover. |
| `auto` | Unconditional link, matching the historical Laravel/Socialite tutorial pattern. | **Avoid in production.** Only safe when the provider universe is fully trusted (e.g. SSO inside a closed org). |

#### Linking and unlinking from the profile page

Mount the manage component on whatever profile/settings page your app uses:

```blade
<livewire:filament-panel-base::auth.manage-social-accounts />
```

It lists each enabled provider as either "Connected" (with a Disconnect button) or "Available" (with a Connect button). Disconnect is automatically blocked when removing the last sign-in method would lock the user out — they have to set a password first.

#### Customising attribute mapping

Subscribe to `SocialAccountMapping` to mutate what gets persisted before either the User or the `SocialAccount` row is written:

```php
use Codenzia\FilamentPanelBase\Auth\Events\SocialAccountMapping;

Event::listen(SocialAccountMapping::class, function (SocialAccountMapping $event): void {
    $event->userAttributes['avatar_url'] = $event->socialUser->getAvatar();
    $event->userAttributes['locale'] = $event->socialUser->getRaw()['locale'] ?? null;
});
```

`$event->userAttributes` is only persisted when `$event->creatingUser === true`. `$event->socialAccountAttributes` is persisted every time a `social_accounts` row is written (signup, link, or re-link).

For post-persistence side effects (welcome emails, audit logging) use `SocialUserLinked` instead — the `linked` flag is `true` on the first link/signup and `false` on returning sign-in.

### Registration policies

`AuthenticationSettings` controls **who may create an account**. Mix and match — the controls compose:

| Goal | How |
|---|---|
| **Open** — anyone can register | `registration_mode = 'open'` (default) |
| **Moderated** — admin must approve | `registration_mode = 'moderated'` → new users land `pending`; `EnsureUserApproved` blocks login until approved, `AccountApprovedNotification` emails them |
| **Closed** — no self-signup | Don't enable Filament's `->registration()` page; admins create/invite accounts |
| **Domain-restricted** — only `@acme.com` (and subdomains) | `allowed_email_domains = ['acme.com']` (empty = any domain) |
| **No throwaway emails** | `disposable_email_blocking = true` (default) |

The email-domain allowlist is enforced by the `AllowedEmailDomain` validation rule and is look-alike safe (`notacme.com` does **not** satisfy an `acme.com` allowlist). Set it three ways — admin **Authentication** settings page, the fluent API, or an env fallback:

```php
FilamentPanelBasePlugin::make()
    ->withAuthentication(fn ($auth) => $auth
        ->moderation()                          // require admin approval
        ->allowedEmailDomains(['acme.com'])     // staff-only signup (+ subdomains)
        ->disposableEmailBlocking()             // reject throwaway providers
    );
```

```dotenv
# config fallback, used before settings are migrated / when the DB is unavailable
PANEL_ALLOWED_EMAIL_DOMAINS="acme.com,acme.io"
```

### Auth settings page (admin UI)

The plugin ships a Filament page that surfaces every `AuthenticationSettings` field — registration mode, identifier, verification, sign-up email-domain allowlist, OTP driver/lifetime, social providers, email-linking policy, throttle limits — grouped into sections so admins don't need to edit DB rows directly.

#### Authorisation (REQUIRED — fail-closed default)

This page controls authentication policy for the whole app, so it is **fail-closed by default**: `ManageAuthenticationSettings::canAccess()` returns `false`. Registering the page on a panel does **not** expose it — a host-side subclass with its own authorisation check is required.

**With `bezhansalleh/filament-shield`:**

```php
namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Codenzia\FilamentPanelBase\Auth\Filament\Pages\ManageAuthenticationSettings;

class AuthSettings extends ManageAuthenticationSettings
{
    use HasPageShield;
}
```

**With a simple Gate/ability check (no shield):**

```php
namespace App\Filament\Pages;

use Codenzia\FilamentPanelBase\Auth\Filament\Pages\ManageAuthenticationSettings;

class AuthSettings extends ManageAuthenticationSettings
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-auth-settings') ?? false;
    }
}
```

Then register your subclass:

```php
FilamentPanelBasePlugin::make()
    ->withFilamentAuthSettingsPage(\App\Filament\Pages\AuthSettings::class);
```

Calling `->withFilamentAuthSettingsPage()` with no argument is intentionally a no-op for end users: the page registers on the panel, but `canAccess()` still returns `false`. Always subclass — even in trusted internal panels — so the security check is local to your repo and visible in code review.

Already maintaining your own settings page (the deprecated `RegistrationSettings`-backed pattern)? See [Legacy: `RegistrationSettings` (deprecated)](#legacy-registrationsettings-deprecated) below for the step-by-step swap.

### Middleware

| Middleware | Description |
|---|---|
| `SetLocale` | Detects locale from session/cookie, validates against `ProvidesLocales` provider, then propagates the chosen code onto `Carbon`, `CarbonImmutable`, `Number::useLocale()`, and the spatie-translatable active locale so the whole formatting stack moves in lockstep with the UI |
| `SetCountry` | Auto-detects country from IP using geo API, stores in session |
| `SetCurrency` | Sets active currency from country relationship or session |
| `EnsureUserApproved` | Blocks suspended/pending users (requires `HasModerationStatus` contract) |
| `ThrottleAuth` | Per-IP rate limit for native HTTP auth routes (OAuth redirect/callback). Livewire-backed pages use the `ThrottlesAuthAttempts` trait instead — see [Auth throttling](#auth-throttling). |

### Localisation

The package treats locale handling as a layered concern — middleware, routing, model traits, vendor overrides — instead of one big switcher. Everything below works without `filament/translations` or `laravel-lang/lang` installed (those packages improve translation coverage but are not required for the mechanics to function).

#### Declaring locales

```php
// config/filament-panel-base.php
'locale' => [
    'available' => ['en', 'ar', 'fr'],         // codes the user can switch to
    'detection_order' => ['session', 'cookie', 'config'],
    'routes' => [
        'enabled' => true,                      // ships `locale.switch` named route
        'prefix' => '',
        'middleware' => ['web'],
    ],
],
```

`available` doubles as the allowlist for both `SetLocale` middleware and the shipped `locale.switch` controller — only codes listed here can become the active locale, so a malformed URL like `/locale/zz` is silently ignored instead of crashing.

For dynamic locales pulled from the database, register a class implementing `Codenzia\FilamentPanelBase\Contracts\ProvidesLocales` and reference it via `locale.provider`. The contract returns `['ar' => ['native' => 'العربية', 'dir' => 'rtl', 'flag' => 'sa'], ...]`.

#### `locale.switch` route

`Codenzia\FilamentPanelBase\Http\Controllers\LocaleController::switch` backs the `locale.switch` named route. The bundled `<x-filament-panel-base::locale-switcher>` view points at it by default (`switchRoute` prop), so the dropdown works the moment the plugin is registered — no host wiring. To replace it with your own controller, set `locale.routes.enabled = false` and register a `Route::get(...)->name('locale.switch')` yourself.

#### Carbon, `Number`, and translatable content stay in sync

After `App::setLocale($locale)`, the `SetLocale` middleware also calls:

- `Carbon::setLocale($locale)` and `CarbonImmutable::setLocale($locale)` — fixes `->diffForHumans()`, `->translatedFormat(...)`, and date diffs that would otherwise stay in the previously-set process locale.
- `Number::useLocale($locale)` (when present) — fixes `Number::currency()`, `Number::ordinal()`, `Number::percentage()`, etc.
- `session(['spatie_translatable_active_locale' => $locale])` — switches the active locale for `spatie/laravel-translatable` content so translatable Filament fields default to the same language as the UI.

No host code is required to opt in; the propagation runs on every request that hits the panel.

#### RTL auto-toggle

Filament v4's base layout reads `__('filament-panels::layout.direction')` to populate `<html dir="...">`. The package ships minimal `layout.direction` overrides for the canonical RTL locales under the `filament-panels` namespace:

- `ar` (Arabic)
- `he` (Hebrew)
- `fa` (Persian / Farsi)
- `ur` (Urdu)

Declaring any of these in `locale.available` flips the entire panel to RTL the moment a user picks the locale — sidebars on the right, modal close buttons on the left, navigation chevrons mirrored. No additional packages required.

If you maintain your own list of RTL locales (e.g. a niche dialect), pair it with `SetLocale::isRtlLocale(string $code): bool`, which exposes the same allowlist (`ar, he, fa, ur, ps, sd, yi, ku, dv`) used by the config-fallback dropdown payload.

#### Validation translations

Adding `ar` to `locale.available` is half the story — Laravel's validator looks for `lang/ar/validation.php` in the host's resources, and if it doesn't exist, every validation error falls back to English. Two strategies:

1. **Production-quality translations.** `composer require laravel-lang/lang` ships community-maintained translations for 70+ locales. Run `php artisan lang:add ar` and you're done.

2. **Quick scaffold.** When you need a starting template (or the locale isn't covered by `laravel-lang/lang`):

   ```bash
   php artisan filament-panel-base:scaffold-validation                # uses config('filament-panel-base.locale.available')
   php artisan filament-panel-base:scaffold-validation ar fr de       # explicit codes
   php artisan filament-panel-base:scaffold-validation ar --force     # overwrite existing
   ```

   Seeds each target with Laravel's bundled English `validation.php`, ready to translate. Skips files that already exist unless `--force` is passed.

#### Per-user preferred locale

For notifications, mark your User model with the `HasPreferredLocale` trait:

```php
use Codenzia\FilamentPanelBase\Concerns\HasPreferredLocale;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasLocalePreference
{
    use HasPreferredLocale;
    use Notifiable;

    // Optional — defaults to the 'locale' column.
    protected string $preferredLocaleAttribute = 'ui_lang';
}
```

Laravel's `NotificationSender` wraps every `Notification::send($user, ...)` in `withLocale($user->preferredLocale(), ...)` whenever the notifiable implements `HasLocalePreference`. Approval emails, password resets, OTP messages all dispatch in the user's chosen language with no per-notification code. The trait falls back to `config('app.locale')` when the column is null/empty so a missing preference never short-circuits the wrap mid-send.

### Legacy: `RegistrationSettings` (deprecated)

`Codenzia\FilamentPanelBase\Settings\RegistrationSettings` is the legacy settings group (`registration.*` keys) with only two fields — `registration_mode` and `require_email_verification`. It is **deprecated since 2.0** and retained solely for back-compat with apps that import the class directly.

**New code should target [`AuthenticationSettings`](src/Auth/Settings/AuthenticationSettings.php) instead** — same two fields plus everything else the auth module exposes (OTP driver, social providers, email-linking policy, throttle limits, …). The admin UI is the in-plugin [Auth settings page](#auth-settings-page-admin-ui) — no more hand-rolled "Manage Registration Settings" pages.

**Migrating an existing app:**

1. Add `->withFilamentAuthSettingsPage(\App\Filament\Pages\AuthSettings::class)` (or `discoverPages`) for the in-plugin page — see the [Auth settings page](#auth-settings-page-admin-ui) section for the shield-subclass pattern.
2. Delete your hand-rolled page + Blade view.
3. Stop seeding the `registration.*` group — `panel-base`'s settings migration already seeds `auth.*` defaults.
4. Update any code that reads `app(RegistrationSettings::class)->registration_mode` to read from `AuthenticationSettings` instead.
5. If you rely on Filament Shield permissions, rename the page permission (e.g. `View:ManageRegistrationSettings` → `View:AuthSettings`) via a one-off migration so existing roles carry over without a manual re-seed.

The deprecated class will be removed in the next major release.

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
| `HasPreferredLocale` | Implements Laravel's `HasLocalePreference` so notifications auto-dispatch in the user's chosen language — see [Localisation](#localisation) |

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
<x-filament-panel-base::country-select :countries="$countries" wire-model="country_id" />
<x-filament-panel-base::country-code-select :countries="$countries" wire-model="country_code" />
<x-filament-panel-base::phone-input :countries="$countries" country-code-model="country_code" phone-model="whatsapp" />
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
<x-filament-panel-base::country-switcher :mode="$countryDropdownMode" />
<x-filament-panel-base::currency-switcher />
<x-filament-panel-base::locale-switcher :locales="$locales" :currentLocale="$currentLocale" />
<x-filament-panel-base::dark-mode-toggle />
```

The switchers read view-shared data from middleware (`$availableCountries`, `$currentCountry`, `$availableCurrencies`, `$currentCurrency`, `$currentCurrencyMode`) and require routes named `country.switch`, `currency.switch`, and `locale.switch` in the consuming app.

**Shared switcher props:**

| Prop | Default | Description |
|---|---|---|
| `align` | `'end'` | Horizontal anchor of the dropdown: `'start'` (left in LTR) or `'end'` (right in LTR). |
| `relative` | `true` | Whether the wrapper element is a CSS positioning context. Set to `false` to let the dropdown position relative to a parent `relative` container instead. |

**Mobile menu example** — use `:relative="false"` with `align="start"` so all dropdowns anchor to a shared `relative` container, preventing overflow on narrow screens:

```blade
<div class="relative flex items-center gap-3">
    <x-filament-panel-base::country-switcher :relative="false" align="start" />
    <x-filament-panel-base::currency-switcher :relative="false" align="start" />
    <x-filament-panel-base::locale-switcher :relative="false" align="start" :locales="$locales" :currentLocale="$currentLocale" />
</div>
```

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

    'branding' => [
        // Render a small "Powered by Codenzia" footer on every panel page
        // and via <x-filament-panel-base::powered-by /> on non-Filament pages.
        // Set CODENZIA_BRANDING=false in .env to hide.
        'powered_by_enabled' => env('CODENZIA_BRANDING', true),
    ],
];
```

## Branding

A subtle **"Powered by Codenzia"** credit line is rendered on every Filament panel page automatically — no configuration needed. The package registers a `PanelsRenderHook::FOOTER` hook in its service provider that injects the credit into the panel chrome below the page content.

### Where it appears

- Every page inside a Filament panel (admin, dashboard, customer panels): admin index, resource list/create/edit/view, custom pages, settings pages, login / register / password-reset auth pages.

### Where it does not appear automatically

- Pages that live **outside** a Filament panel — your front-of-site Livewire/Blade pages, marketing routes, raw public endpoints. For those, drop the matching Blade component into your root layout:

  ```blade
  <x-filament-panel-base::powered-by />
  ```

  Same wording, same styling, same opt-out via `CODENZIA_BRANDING`.

### Hiding the credit

Set the env var in `.env`:

```dotenv
CODENZIA_BRANDING=false
```

The hook checks this on every request, so no cache clear is needed when toggling. Useful when an app graduates from a Codenzia-controlled demo to a customer-owned deployment that wants its own branding.

### Styling

The default markup uses Tailwind utilities (`text-xs`, `text-gray-400 dark:text-gray-600`) and respects your panel's primary color via `hover:text-primary-500`. To restyle, publish the package's `powered-by.blade.php` component or override the render hook in your own `AppServiceProvider`:

```php
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

// In AppServiceProvider::boot()
FilamentView::registerRenderHook(
    PanelsRenderHook::FOOTER,
    fn (): string => Blade::render('<your custom footer markup here>')
);
```

The most recently-registered hook wins, so a host override replaces the package default cleanly.

## Session Expiry (419) Handling

When a session/CSRF token expires, Laravel returns HTTP **419** — Filament/Livewire shows a *"This page has expired"* modal on AJAX requests, and a bare *"Page Expired"* error on full-page submits. Both are jarring. This package replaces them with a clean redirect to the login screen, **on by default** for every consuming app — no wiring required.

Two complementary pieces, both gated by `config('filament-panel-base.session_expiry.enabled')`:

- **Backend** — a renderable catches the 419 (`TokenMismatchException`) on full-page requests and redirects to the login URL with a `warning` flash (`__('filament-panel-base::auth.session_expired')`). Livewire/AJAX 419s are deliberately left as 419 for the client-side hook to handle.
- **Frontend** — a `BODY_END` render hook injects a Livewire `request` hook that intercepts 419 responses on every panel page, stores the current URL in `sessionStorage('redirect_after_login')`, redirects to login, and suppresses the default expiry modal.

### Redirect target

By default the user is sent to the **current Filament panel's own login URL** (e.g. `/admin/login`), falling back to the app's named `login` route, then `/`. Override it explicitly:

```php
// config/filament-panel-base.php
'session_expiry' => [
    'enabled' => env('PANEL_SESSION_EXPIRY', true),
    'redirect_to' => '/login', // null = smart default (panel login → route('login') → '/')
],
```

### Disabling

Set `PANEL_SESSION_EXPIRY=false` in `.env` (or `session_expiry.enabled => false`) to opt out and restore the default Livewire modal / 419 page — useful for apps that handle 419 themselves.

### Front-of-site (non-panel) pages

Filament panel pages get the interceptor automatically. For Livewire pages rendered **outside** a panel (e.g. a custom public login/register layout), drop the bundled component into your layout:

```blade
<x-filament-panel-base::session-expiry-handler />
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

## Translation Manager UI (Optional)

The package includes a built-in Translation Manager that lets you view, edit, and scan all `__()` / `trans()` language strings from the admin panel — no file editing required. Translations are stored in the database via [spatie/laravel-translation-loader](https://github.com/spatie/laravel-translation-loader) and override file-based translations at runtime.

### When to use this

Use this when you need a **non-developer-friendly UI** to manage static language files (e.g. `lang/en/messages.php`, `lang/ar.json`). This is different from `spatie/laravel-translatable` which handles database content.

**Key features:**
- **Codebase scanner** — automatically finds all `__()`, `trans()`, `@lang()`, `Lang::get()` calls
- **Dynamic locales** — reads available languages from your `ProvidesLocales` provider (no hardcoded config)
- **Per-language workflow** — access translations from your Language resource's action group, scoped to a single locale
- **Configurable scanner** — scan extra file types (`js`, `ts`, `vue`) and functions (`$t`, `i18n.t`)
- **DB overrides** — database translations take precedence over file translations, with caching

### Step 1: Publish migrations and config

```bash
php artisan filament-panel-base:enable-translations
```

This publishes the `spatie/laravel-translation-loader` migration and config.

### Step 2: Run migrations

```bash
php artisan migrate
```

### Step 3: Configure the translation model

In `config/translation-loader.php`, point the model to the panel-base Translation model:

```php
'model' => Codenzia\FilamentPanelBase\Models\Translation::class,
```

### Step 4: Opt in per panel

Add `->withTranslations()` to `FilamentPanelBasePlugin::make()` in the panel(s) where you want the translation routes registered:

```php
->plugins([
    FilamentPanelBasePlugin::make()
        ->withTranslations()
        ->settingsUsing(fn () => app(\App\Settings\GeneralSettings::class)),
])
```

The Translations resource is hidden from sidebar navigation by default. It is designed to be accessed from a Language resource (see Step 5). Panels without `->withTranslations()` are unaffected.

### Step 5: Add to your Language resource

Add the **Manage Translations** action to your Language resource's record actions and optionally the **Scan** action to the page header:

```php
use Codenzia\FilamentPanelBase\Filament\Resources\TranslationResource;

// In your LanguageResource table():
->recordActions([
    Actions\ActionGroup::make([
        Actions\EditAction::make()->slideOver(),
        TranslationResource::manageAction(), // opens translations scoped to this language
        Actions\DeleteAction::make(),
    ]),
])

// In your ManageLanguages page getHeaderActions() (optional):
TranslationResource::scanHeaderAction(),
```

When the user clicks **Manage Translations** on a language, the translations page opens scoped to that locale — the table shows that language's text and the edit form only shows the relevant textarea.

### Step 6: Scan your codebase for translation keys

```bash
php artisan translations:scan
```

This scans your project for all `__()`, `trans()`, `@lang()` calls and populates the database with initial values from your existing language files. Re-run whenever you add new translation keys.

You can also scan from the admin UI using the **Scan** button in the Translations page header.

### Customising the scanner

Override scan paths, file extensions, and translation functions via config:

```php
// config/filament-panel-base.php
'translations' => [
    'navigation_group' => 'Settings',
    'navigation_sort' => 11,
    'navigation_icon' => 'heroicon-o-language',
    'scan_paths' => null,           // null = [app_path(), resource_path('views')]
    'scan_extensions' => ['php'],   // add 'js', 'ts', 'vue' for frontend files
    'scan_functions' => [],         // extra function names, e.g. ['$t', 'i18n.t']
],
```

The scanner always matches `__()` plus the PHP-specific grouped functions (`trans()`, `@lang()`, `Lang::get()`, etc.). The `scan_functions` config adds extra function names for JSON-style translation calls in other languages.

## Demo Page (Optional)

A drop-in `/demo` Livewire page for sales demos, QA, and reviewer walkthroughs. Auto-discovers your `app/Models/` classes for a stats grid, lists every user with a one-click "login as" button (super_admins blocked by default), shows optional Standard/Demo seed buttons when those seeders exist, and renders a footer with build date and PHP/Laravel/Filament versions. The page is gated by a single shared password sourced from `.env` (and optionally a DB row — see the Demo Settings page below).

### When to use this

You're shipping a Filament app to internal QA, sales prospects, or auditors and want a single URL that introduces every demo account and seed dataset without typing credentials.

### Enable per host

In `.env`:

```env
FILAMENT_PANEL_BASE_DEMO_ENABLED=true
APP_DEMO_PAGE_PWD=replace-with-a-random-string
```

That's it — the package registers a `GET /demo` route automatically when enabled. Defaults to `web` middleware, the bundled standalone layout (Tailwind via CDN so it renders regardless of your CSS build state), and the included Livewire component.

> **Always set `APP_DEMO_PAGE_PWD`.** With no password configured, `/demo` stays locked by default (the gate refuses every submission). See [Empty-password behavior](#empty-password-behavior) below if you need to opt into the legacy auto-unlock on fresh installs.

### Four customization levels

The defaults work for a typical app. When you need more, lift up only the layer that's wrong — you don't have to fork the whole page.

**1. Config-driven stat list.** Override the auto-discovered model counts:

```php
// config/filament-panel-base.php
'demo' => [
    'stats' => [
        ['model' => \App\Models\Property::class, 'label' => 'Listings', 'icon' => 'heroicon-o-home'],
        ['model' => \App\Models\Inquiry::class,  'label' => 'Inquiries', 'icon' => 'heroicon-o-envelope'],
    ],
    // Or keep auto-discovery and just hide noisy models:
    'exclude_models' => [\App\Models\PivotJunk::class, 'PasswordReset'],
],
```

**2. Publishable view.** Customize the markup without writing PHP:

```bash
php artisan vendor:publish --tag=filament-panel-base-views
```

Then edit `resources/views/vendor/filament-panel-base/livewire/demo/page.blade.php`.

**3. Named Livewire section slots.** Plug your own Livewire components into the page chrome without forking it. Four slots: `before_stats`, `after_stats`, `before_users`, `after_users`.

```php
// config/filament-panel-base.php
'demo' => [
    'sections' => [
        'before_stats' => \App\Livewire\AqarkomCountryFilterSection::class,
        'after_stats'  => \App\Livewire\AqarkomMarketSnapshot::class,
    ],
],
```

The page renders `@livewire($component)` at each slot — your component dispatches Livewire events that the page (or its subclass) listens to via `#[On(...)]`.

**4. Whole-component swap.** Subclass `DemoPage` and override `collectStats()`, `collectUsers()`, or `canLogInAs()`:

```php
// app/Livewire/DemoPage.php
namespace App\Livewire;

use Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage as BaseDemoPage;

class DemoPage extends BaseDemoPage
{
    protected function collectUsers(): array
    {
        // Limit to seeded demo accounts, decorate with domain counts
        return User::whereIn('email', ['superadmin@example.test', 'agent@example.test'])
            ->withCount(['properties', 'inquiries'])
            ->get()
            ->map(fn ($u) => /* ... return the expected shape ... */)
            ->all();
    }

    protected function canLogInAs(Model $user): bool
    {
        // Hard email allowlist — replaces a bespoke POST /demo/login throttle gate
        return in_array($user->email, self::DEMO_EMAILS, true);
    }
}
```

Then wire it in `AppServiceProvider::boot()`:

```php
config(['filament-panel-base.demo.component' => \App\Livewire\DemoPage::class]);
```

The route resolves this lazily via `$this->app->booted()`, so your host config wins over the package default.

### Seed buttons

The page conditionally renders Standard / Demo seed buttons when the corresponding class exists in your app. Override the seeder map if your classes have different names:

```php
'demo' => [
    'seeders' => [
        'standard' => 'Database\\Seeders\\StandardSeeder',
        'demo' => 'Database\\Seeders\\DemoSeeder',
    ],
],
```

Both buttons trigger `migrate:fresh` + the configured seeder, then auto-login the first admin (or the one identified by `demo.admin_email`).

---

## Demo Settings Page (Optional)

A Filament admin page (`ManageDemoSettings`, registered under the **Settings** navigation group) that lets admins view, regenerate, and share the `/demo` password without touching `.env`. Backed by a singleton `demo_settings` table with an encrypted password cast and a `last_used_at` timestamp updated on every successful gate unlock.

### When to use this

You're sharing `/demo` with prospects, you have multiple apps and don't want to memorize a different `.env` value for each one, and you want a "rotate now" button rather than redeploying when a password leaks.

### Enable per panel

```bash
php artisan vendor:publish --tag=filament-panel-base-demo-migrations
php artisan migrate
```

Then opt in via the plugin:

```php
FilamentPanelBasePlugin::make()
    ->withDemoSettingsPage()
```

### Password resolution order

`DemoPage::expectedPassword()` resolves in this order:

1. `demo_settings.password` (DB row, encrypted cast) if the table exists and the value is set
2. `APP_DEMO_PAGE_PWD` env var
3. `null` → **gate stays locked** (page never renders the demo content without a password)

The `.env` fallback means a fresh install isn't locked out before the migration runs, and hosts that never set up the DB row keep the env-only behavior.

### Empty-password behavior

When `expectedPassword()` resolves to `null` or `''`, `/demo` stays locked by default — the password form is shown and `unlock()` rejects every submission. `/demo` is never public unless a password is explicitly set.

If you actively want the old behavior (auto-unlock when no password is configured — handy on fresh local installs), opt in:

```env
FILAMENT_PANEL_BASE_DEMO_ALLOW_EMPTY=true
```

Or per app:

```php
// config/filament-panel-base.php
'demo' => [
    'allow_empty_password' => true,
],
```

Leave it off for staging/production deployments. The flag exists for local development convenience, not as a way to expose `/demo` without a password.

### CLI: `demo:password`

For SSH access or when the admin UI isn't enabled, manage the password from the command line. Writes go to the `demo_settings` DB row (encrypted cast) — same source the gate reads from first.

```bash
# Show the current password and its source (DB / env / unset)
php artisan demo:password

# Generate a fresh 16-char random password, save it, print it
php artisan demo:password --regenerate

# Set the password to a specific value
php artisan demo:password --set='your-chosen-value'
```

Requires the `demo_settings` migration to have run (`php artisan vendor:publish --tag=filament-panel-base-demo-migrations && php artisan migrate`); read-only `php artisan demo:password` falls back to the env var when the table doesn't exist.

## Analytics

Visitor + auth-event tracking with a ready-to-mount AnalyticsPage. Off by default — call `->withAnalytics()` on the plugin to turn it on.

### Quick start

```php
// AppServiceProvider::boot — global config
FilamentPanelBasePlugin::make()
    ->withAnalytics()           // sensible defaults
    ->withFilamentAnalyticsPage(); // mounts /admin/analytics

// then:
php artisan migrate
```

That's it. The three analytics tables (`visits`, `visits_daily`, `auth_events`) and the settings rows are auto-loaded — no `vendor:publish` required. Visit `/admin/analytics` to see the dashboard.

### Plugin API

```php
->withAnalytics(fn ($a) => $a
    ->trackVisits()                  // default true
    ->trackAuthEvents()              // default true
    ->ipAnonymization('truncate')    // 'none' | 'truncate' | 'hash'
    ->retainRawDays(30)              // raw visits pruned after N days
    ->retainAggregatedDays(365)      // visits_daily kept N days
    ->botFilter()                    // tag bot UAs is_bot=true (excluded from widgets)
    ->writeQueue('analytics')        // dispatch RecordVisitJob to this queue (null = sync)
)
```

### What gets recorded

**`visits`** (every page view, retained `retain_raw_days`):
`id`, `session_id`, `user_id`, `tenant_id`, `tenant_type`, `panel`, `route_name`, `path`, `method`, `status`, `referrer_host`, `country_code`, `ip_hash`, `device_type`, `browser`, `platform`, `is_bot`, `duration_ms`, `created_at`.

**`auth_events`** (small rows, retained `retain_aggregated_days`):
type is one of `login.success`, `login.failed`, `logout`, `register`, `otp.requested`, `otp.verified`, `social.login`, `moderation.*`, `password.reset`, `two_factor.enabled`, `two_factor.disabled`, `two_factor.failed`, `two_factor.recovery_used`.

### Scheduled commands

Boot automatically when `runningInConsole()`:

| Command | Cadence | Job |
|---|---|---|
| `filament-panel-base:analytics:rollup` | Hourly, no overlap | Rebuild `visits_daily` buckets for the affected dates. |
| `filament-panel-base:analytics:prune` | Daily at 03:15, no overlap | Chunk-delete `visits` rows older than `retain_raw_days`, `visits_daily` + `auth_events` older than `retain_aggregated_days`. |

### Tenant scoping

Widgets and the rollup are tenant-scoped via `filament()->getTenant()`. If your panel uses Filament tenancy, each tenant's admins see only their own visits/auth events.

### Subclassing the page for Shield / custom access

```php
// Your subclass
namespace App\Filament\Admin\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Codenzia\FilamentPanelBase\Analytics\Filament\Pages\AnalyticsPage as Base;

class AnalyticsPage extends Base
{
    use HasPageShield;
}

// In your panel
->withFilamentAnalyticsPage(\App\Filament\Admin\Pages\AnalyticsPage::class)
```

### Privacy + GDPR

- `ip_anonymization='truncate'` (default) zeroes the last octet (IPv4) / last 80 bits (IPv6) before hashing — pseudonymous but not reversible.
- `ip_anonymization='hash'` stores only `sha256(raw_ip)`.
- `ip_anonymization='none'` stores the raw IP (hashed for the column type) — use only if your legal posture allows it.
- Retention is enforced by the prune command; nothing leaks indefinitely once `retain_raw_days` passes.

---

## Two-Factor Authentication

TOTP enrolment + post-login challenge with recovery codes. Off by default — call `->withTwoFactor()` on the plugin to turn it on.

### Install dependencies

```bash
composer require pragmarx/google2fa bacon/bacon-qr-code
```

Both are listed as `suggest:` — install them only if you use 2FA. The services throw a clear `RuntimeException` if missing.

### Quick start

```php
// 1. AppServiceProvider::boot
FilamentPanelBasePlugin::make()
    ->withTwoFactor()
    ->withFilamentTwoFactorChallengePage();  // optional: render challenge inside panel chrome

// 2. User model
use Codenzia\FilamentPanelBase\TwoFactor\Concerns\HasTwoFactorAuthentication;

class User extends Authenticatable
{
    use HasTwoFactorAuthentication;
}

// 3. Profile slide-over tab — extend your PanelProvider
use Codenzia\FilamentPanelBase\TwoFactor\Concerns\HasTwoFactorProfileTab;

class AdminPanelProvider extends BasePanelProvider
{
    use HasTwoFactorProfileTab;

    protected function getProfileFormTabs(): array
    {
        return [
            ...parent::getProfileFormTabs(),
            $this->getTwoFactorProfileTab(),
        ];
    }
}

// 4. Run migrations — adds the 3 columns to your users table
php artisan migrate
```

### Plugin API

```php
->withTwoFactor(fn ($tf) => $tf
    ->issuer('Acme Inc.')             // shown in the authenticator app entry
    ->digits(6)                       // 6, 7, or 8 (Google Authenticator wants 6)
    ->period(30)                      // TOTP step in seconds (RFC default 30)
    ->acceptanceWindow(1)             // accept ±N step codes (clock-skew tolerance)
    ->recoveryCodeCount(8)            // 8 single-use codes per user
    ->requireForRoles(['super_admin']) // enforce via RequireTwoFactor middleware
    ->rememberDevice(true, days: 30)  // long-lived cookie to skip repeat challenges
)
```

### How the post-login challenge works

1. User submits credentials → `Login` Livewire validates them.
2. If the user has `hasTwoFactorEnabled() === true`, the credentials pass but `Auth::login()` is **not** called. Instead the user id + remember flag are stashed in the session under `codenzia.two_factor_challenge`.
3. The user is redirected to `route('two-factor.challenge')` (`/two-factor-challenge`).
4. The user enters either a 6-digit TOTP code or a 10-10 recovery code. The challenge component verifies via the trait's `verifyTwoFactorCode()`, calls `Auth::login()`, regenerates the session, and redirects to the intended URL.
5. If they tick "Trust this device for 30 days", a HMAC cookie keyed on `(user_id, secret, app_key)` is queued. Regenerating the 2FA secret or disabling 2FA invalidates it automatically.

### Mandatory enrolment for specific roles

```php
->withTwoFactor(fn ($tf) => $tf->requireForRoles(['super_admin', 'finance']));

// Wire the middleware in your panel
$panel->authMiddleware([
    Authenticate::class,
    \Codenzia\FilamentPanelBase\TwoFactor\Http\Middleware\RequireTwoFactor::class,
]);
```

`RequireTwoFactor` redirects matching users to the challenge page on every request until they enrol. It needs `spatie/laravel-permission`'s `hasAnyRole()` on your user model; without it, the middleware fails open (no lockout).

### Database columns

The auto-loaded migration adds these to your `users` table (idempotent via `Schema::hasColumn` guards — safe to re-run, safe alongside an existing Fortify install since the names match exactly):

| Column | Type | Notes |
|---|---|---|
| `two_factor_secret` | `text` nullable | Encrypted at rest via accessor. |
| `two_factor_recovery_codes` | `text` nullable | Encrypted JSON of bcrypt hashes. |
| `two_factor_confirmed_at` | `timestamp` nullable | Null until the user verifies one code. |

### Events

| Event | Fired when |
|---|---|
| `TwoFactorEnabled` | User confirmed enrolment with a valid TOTP code. |
| `TwoFactorDisabled` | User turned 2FA off (only fires if it was enabled). |
| `RecoveryCodeUsed` | A recovery code was consumed during a challenge. Send a "we noticed" email here. |
| `TwoFactorChallengeFailed` | Invalid code submitted at challenge. Auto-persisted as `auth_events.type = two_factor.failed` for dashboards. |

---

## Sessions & Devices

Self-service active-session list with per-row revoke and "sign out everywhere else". Off by default — call `->withSessionManagement()` on the plugin to turn it on.

### Requirement: database session driver

This module reads Laravel's `sessions` table directly. **`SESSION_DRIVER=database`** is required. With any other driver the profile tab degrades to a friendly "configure database sessions to see this" notice — nothing crashes, but the list stays empty.

```bash
# .env
SESSION_DRIVER=database

php artisan session:table   # if you don't already have one
php artisan migrate
```

### Quick start

```php
// 1. AppServiceProvider::boot
FilamentPanelBasePlugin::make()->withSessionManagement();

// 2. Profile slide-over tab — extend your PanelProvider
use Codenzia\FilamentPanelBase\Sessions\Concerns\HasSessionManagementProfileTab;

class AdminPanelProvider extends BasePanelProvider
{
    use HasSessionManagementProfileTab;

    protected function getProfileFormTabs(): array
    {
        return [
            ...parent::getProfileFormTabs(),
            $this->getSessionManagementProfileTab(),
        ];
    }
}
```

### Plugin API

```php
->withSessionManagement(fn ($s) => $s
    ->notifyOnNewDevice()             // fire NewDeviceLogin on unseen IP+UA fingerprints
    ->idleThresholdMinutes(15)        // sessions older than this show as "last active X min ago"
    ->allowLogoutOtherDevices()       // expose the "sign out everywhere else" button
)
```

### `NewDeviceLogin` event

A `DetectNewDeviceLogin` listener subscribes to `Illuminate\Auth\Events\Login`. On every successful login it computes `sha256(ip|user_agent)` and looks for a matching existing row in the `sessions` table for that user. If none is found, it dispatches `Codenzia\FilamentPanelBase\Sessions\Events\NewDeviceLogin($user, $ipAddress, $userAgent)` — wire your own listener to email the user.

```php
// In your EventServiceProvider or a Listener
use Codenzia\FilamentPanelBase\Sessions\Events\NewDeviceLogin;

Event::listen(NewDeviceLogin::class, function (NewDeviceLogin $event): void {
    Mail::to($event->user)->send(new NewDeviceLoginMail($event));
});
```

The fingerprint is intentionally coarse (IP + UA, not browser cookies) so private-mode browsing from a known device doesn't trigger false positives.

### What the user sees

For each active session: device-type icon (desktop/mobile/tablet), browser + OS, IP address, "active now" or "last active X minutes ago", a "Current" badge on the row matching `session()->getId()`, and a Revoke button (or Sign out for the current row).

If there's more than one row, a "Sign out everywhere else" button appears at the top of the list.

---

## Command Palette (Cmd-K)

A global Cmd-K modal that augments Filament's chrome with navigation jumps and recently viewed records. Off by default — call `->withCommandPalette()` on the plugin to turn it on.

### Quick start

```php
FilamentPanelBasePlugin::make()->withCommandPalette();
php artisan migrate   // creates command_palette_recent_views table
```

Once enabled, pressing **Cmd+K** (macOS) or **Ctrl+K** anywhere on a Filament page opens a search modal. Navigation entries for every Filament resource and page on the current panel appear by default.

### Plugin API

```php
->withCommandPalette(fn ($c) => $c
    ->hotkeyLabel('⌘K')               // displayed hint (rendering only)
    ->recentViewLimit(15)             // max items kept per (user, panel)
    ->trackRecentViews(true)          // auto-record record-page views
)
```

### Recent-record auto-tracking

Wired into `Filament::serving()`. On every served request, if the current Livewire controller is a Filament resource record page (has `getRecord()` + `getResource()`), a row is upserted into `command_palette_recent_views` for the authenticated user. The recorder is best-effort and silently swallows any failure.

Pruning happens at write-time per `(user, panel)` tuple — no scheduled job required.

### Adding your own actions

Implement `CommandPaletteContributor` (or pass a callable / raw array) and register with the singleton registry:

```php
use Codenzia\FilamentPanelBase\CommandPalette\CommandPaletteRegistry;
use Codenzia\FilamentPanelBase\CommandPalette\Contracts\CommandPaletteContributor;
use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;

class QuickActionsContributor implements CommandPaletteContributor
{
    public function actions(?string $query = null): iterable
    {
        return [
            new CommandPaletteAction(
                id: 'action:export-csv',
                label: 'Export users to CSV',
                url: route('admin.users.export'),
                description: 'Download a snapshot of every user as CSV.',
                icon: 'heroicon-o-arrow-down-tray',
                group: 'Actions',
                keywords: ['download', 'spreadsheet'],
            ),
        ];
    }
}

// AppServiceProvider::boot
app(CommandPaletteRegistry::class)->register(new QuickActionsContributor);
```

Or, for a one-shot:

```php
app(CommandPaletteRegistry::class)->register(fn () => [
    new CommandPaletteAction(id: 'quick', label: 'Quick action', url: '/x'),
]);
```

Actions are deduped by `id`, scored against the query (label prefix > label substring > haystack substring), and capped at 50 entries per modal render.

### Keyboard

- `Cmd+K` / `Ctrl+K` — toggle the modal
- `↑` / `↓` — move selection
- `Enter` — open the selected action
- `Esc` — close

All handled by Alpine.js inside the modal — no JS bundle changes.

## Plugin API

```php
FilamentPanelBasePlugin::make()
    // Resolve settings via closure
    ->settingsUsing(fn () => app(GeneralSettings::class))
    // Or by class name
    ->settingsClass(GeneralSettings::class)
    // Enable the translation manager UI for this panel (opt-in)
    ->withTranslations()
    // Register the Demo Settings admin page (opt-in; requires the
    // demo_settings migration — see "Demo Settings Page" above)
    ->withDemoSettingsPage()
    // Analytics module — visitor tracking, auth events, AnalyticsPage
    ->withAnalytics()
    ->withFilamentAnalyticsPage()
    // Two-Factor Authentication — opt-in TOTP + post-login challenge
    ->withTwoFactor()
    ->withFilamentTwoFactorChallengePage()
    // Active session listing in the profile slide-over (requires SESSION_DRIVER=database)
    ->withSessionManagement()
    // Cmd-K command palette mounted on every Filament page in this panel
    ->withCommandPalette()

// Get resolved theme colors (used internally by <x-filament-panel-base::theme-styles />)
FilamentPanelBasePlugin::make()->getThemeColors();
// Returns: ['primary_color' => '#3b82f6', 'danger_color' => '#ef4444', ...]
```

## Requirements

- PHP 8.3+
- Laravel 12+
- Filament v4
- `spatie/laravel-settings` ^3.0 (required, for `AuthenticationSettings` / `RegistrationSettings`)
- `spatie/laravel-permission` (optional, for `NotifiesAdmins` trait)
- `spatie/laravel-translatable` + `lara-zeus/spatie-translatable` (optional, for translatable database content)
- `spatie/laravel-translation-loader` ^2.8 (bundled — activate with `->withTranslations()` for translation manager UI)

## License

This package is dual-licensed:

- **MIT License** — Free for open source projects under an OSI-approved license.
- **Commercial License** — Required for proprietary/commercial projects. Visit [codenzia.com](https://codenzia.com) for details.

See [LICENSE.md](LICENSE.md) for full terms.
