# CLAUDE.md — filament-panel-base

Codenzia global standards apply (see `GitHub/CLAUDE.md`). Package-specific policy below.

## BVT policy (Build Verification Tests)

`tests/BVT/` is the package's thin **existence net** — one shallow check per
shipped surface. Its job is to fail loudly when a Livewire component, Filament
page, analytics widget, or Blade view is renamed, deleted, or starts fataling on
load. It is **not** a place for behaviour coverage.

Rules when touching this package:

- **Every new user-facing surface** (Livewire component, Filament page/widget,
  shipped Blade view) gets a matching entry in the `tests/BVT/` roll-call and,
  where the surface is guest-safe and mountable without a booted panel, a
  `Livewire::test(...)->assertOk()` mount in `LivewireMountSmokeTest`.
- **Keep BVT shallow.** Assert existence, base type, view resolution, clean
  mount — nothing more. Deep behaviour lives in the surface-specific suites
  (`tests/Auth`, `tests/Analytics`, `tests/Sessions`, `tests/TwoFactor`, …).
- Surfaces needing a booted Filament panel (e.g. `<x-filament::icon>` in the
  command palette) or per-request state (tokens, authenticated user, challenge
  session) are covered by **class/view roll-call only** in BVT; their render is
  exercised in their own suite.
- Update `FEATURES.md` when the surface inventory changes.

## Review docs

`fable-*` / `*-full-review*` files are internal review scratch — gitignored,
never commit them.
