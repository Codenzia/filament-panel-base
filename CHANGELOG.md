# Changelog

All notable changes to `filament-panel-base` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **`/demo` Livewire page** (`Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage`). Drop-in landing page with password gate, auto-discovered model-count tiles, users table with one-click "login as", optional Standard/Demo seed buttons (rendered only when the seeder class exists), and a footer showing build date and PHP/Laravel/Filament versions. Opt-in per host via `FILAMENT_PANEL_BASE_DEMO_ENABLED=true` + `APP_DEMO_PAGE_PWD=...`.
- **Demo Settings admin page** (`Codenzia\FilamentPanelBase\Filament\Pages\ManageDemoSettings`). Lets admins view, regenerate, and copy the `/demo` share link without touching `.env`. Singleton `demo_settings` table (encrypted password cast, rotated_at, last_used_at). Opt-in via `FilamentPanelBasePlugin::make()->withDemoSettingsPage()`.
- **DB-first password resolution.** `DemoPage::expectedPassword()` reads `DemoSetting::current()->password` first and falls back to the `APP_DEMO_PAGE_PWD` env var, so existing hosts keep their env-only behaviour until they generate a DB value.
- **`/demo` customization hooks (four levels).** (1) Config-driven stat list via `demo.stats` and `demo.exclude_models`; (2) publishable view via `--tag=filament-panel-base-demo-views`; (3) named Livewire section slots (`before_stats`, `after_stats`, `before_users`, `after_users`) via `demo.sections`; (4) whole-component swap via `demo.component` so hosts subclass the page and override `collectStats()`, `collectUsers()`, `canLogInAs()`.
- **`canLogInAs(Model $user)` hook** on `DemoPage`. Default forbids super_admins; subclasses can tighten (e.g. email allowlist) or loosen. The blade's "Login" button now respects this via `is_super` so denied accounts render `—` instead of failing silently.
- **Deferred route registration.** `bootDemoModule()` now wraps the `/demo` route + Livewire component binding in `$this->app->booted()` so a host can swap `demo.component` from `AppServiceProvider::boot()` before the route is realised.
- **`filament-panel-base-demo-migrations` publish tag.** Ships the `demo_settings` table migration stub alongside the existing auth/social-accounts migrations.
- **Tailwind v4 native auth-page theming.** The shipped Livewire auth views (`login`, `register`, `forgot-password`, `reset-password`, `verify-email-notice`, `verify-otp`, `manage-social-accounts`) and `layouts/auth.blade.php` now use `primary-*` and `surface-*` Tailwind scales backed by runtime CSS variables, so consuming projects can recolor every surface (page body, card, inputs, borders, dividers, OAuth buttons) without publishing the views or rebuilding CSS.
- `theme.css` now exposes `--color-primary-{50..900}` (mirrors `--color-brand-*`, both point at `--site-primary` / `--site-primary-hover` / `--site-brand-*`) plus `--color-surface-{page,card,input,border}` and their `-dark` companions.
- `<x-filament-panel-base::theme-styles />` now injects `--site-surface-{page,card,input,border}{,_dark}` onto `:root` with gray defaults (`#f9fafb` / `#111827` for page, `#ffffff` / `#1f2937` for card, `#ffffff` / `#111827` for input, `#d1d5db` / `#374151` for border). Override per-project via matching `surface_*_color` keys on a settings class implementing `ProvidesThemeColors`.
- Auth inputs gained an explicit `border` width utility (paired with `border-surface-border`) so they render correctly under Tailwind v4 without `@tailwindcss/forms`, plus `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500` for keyboard accessibility.
- README: new "Theming auth pages" section with full variable table and a copy-pasteable `:root { … }` override example.

### Fixed
- Auth-page submit buttons (`bg-primary-600`, `hover:bg-primary-700`, `focus:ring-primary-500`) previously rendered with no background on consuming projects because Tailwind v4 doesn't generate utilities for undefined color scales. The new `primary-*` scale in `theme.css` resolves these references.
- `makeTranslatablePlaceholder()` closure no longer accesses `$component->getLivewire()` on a component whose `$container` is still uninitialized. The inner closure now accepts `$component` as an injected parameter (Filament's `EvaluatesClosures::evaluate()` binds `$this` via `evaluationIdentifier = 'component'`) instead of capturing the definition-time prototype via `use ($component)`. Resolved a 500 error on Filament forms that nest text inputs inside repeater rows (e.g. `MediaSettings` with tabs → repeater → grid → TextInput).

[Unreleased]: https://github.com/Codenzia/filament-panel-base/commits/main

## [0.1.0] - 2026-05-20

### Added
- First tracked release. Early beta. Earlier history not recorded in this changelog — see git log for changes prior to release-tracker adoption.
