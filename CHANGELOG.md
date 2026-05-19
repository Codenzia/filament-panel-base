# Changelog

All notable changes to `filament-panel-base` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Tailwind v4 native auth-page theming.** The shipped Livewire auth views (`login`, `register`, `forgot-password`, `reset-password`, `verify-email-notice`, `verify-otp`, `manage-social-accounts`) and `layouts/auth.blade.php` now use `primary-*` and `surface-*` Tailwind scales backed by runtime CSS variables, so consuming projects can recolor every surface (page body, card, inputs, borders, dividers, OAuth buttons) without publishing the views or rebuilding CSS.
- `theme.css` now exposes `--color-primary-{50..900}` (mirrors `--color-brand-*`, both point at `--site-primary` / `--site-primary-hover` / `--site-brand-*`) plus `--color-surface-{page,card,input,border}` and their `-dark` companions.
- `<x-filament-panel-base::theme-styles />` now injects `--site-surface-{page,card,input,border}{,_dark}` onto `:root` with gray defaults (`#f9fafb` / `#111827` for page, `#ffffff` / `#1f2937` for card, `#ffffff` / `#111827` for input, `#d1d5db` / `#374151` for border). Override per-project via matching `surface_*_color` keys on a settings class implementing `ProvidesThemeColors`.
- Auth inputs gained an explicit `border` width utility (paired with `border-surface-border`) so they render correctly under Tailwind v4 without `@tailwindcss/forms`, plus `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500` for keyboard accessibility.
- README: new "Theming auth pages" section with full variable table and a copy-pasteable `:root { … }` override example.

### Fixed
- Auth-page submit buttons (`bg-primary-600`, `hover:bg-primary-700`, `focus:ring-primary-500`) previously rendered with no background on consuming projects because Tailwind v4 doesn't generate utilities for undefined color scales. The new `primary-*` scale in `theme.css` resolves these references.
- `makeTranslatablePlaceholder()` closure no longer accesses `$component->getLivewire()` on a component whose `$container` is still uninitialized. The inner closure now accepts `$component` as an injected parameter (Filament's `EvaluatesClosures::evaluate()` binds `$this` via `evaluationIdentifier = 'component'`) instead of capturing the definition-time prototype via `use ($component)`. Resolved a 500 error on Filament forms that nest text inputs inside repeater rows (e.g. `MediaSettings` with tabs → repeater → grid → TextInput).

[Unreleased]: https://github.com/Codenzia/filament-panel-base/commits/main
