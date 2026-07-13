# Full Code Review — `codenzia/filament-panel-base`

> **⚠️ SEQUENCED BY MASTER PLAN (2026-07-07):** panel-base is **Wave 1** of `C:\mh2\Projects\Codenzia\GitHub\fable-launch-five-production-plan.md` (workstream S6 — every launch app inherits this package; CRITICAL/HIGH here first, then tag + bump consumers). It also hosts workstream S4 (locale middleware). Read the master plan first.

- **Date:** 2026-07-06
- **Reviewer:** Claude Fable 5
- **Version reviewed:** `0.4.1` (CHANGELOG 2026-07-01; `branch-alias: dev-main → 0.4.x-dev`; no `version` in composer.json — correct for a path/VCS package)
- **Requires:** PHP ^8.3, `filament ^4 || ^5`, spatie settings/translation-loader
- **Coverage note:** Every file in scope read in full — Auth module (Login/Register/OTP/OAuth/reset/validation/drivers/settings), TwoFactor (14 files), Sessions (8 files), Analytics (28 files), CommandPalette (12 files), Providers, Middleware, Filament Pages/Resources, Demo, Services, Support, config, all migrations, blades, and all ~50 test files (~314 cases). This review consolidates three parallel module deep-dives.

---

## Executive Summary

filament-panel-base is the fleet's shared auth/panel backbone (~18 apps) and it is, on the whole, **a well-engineered, security-conscious package**. `declare(strict_types=1)` is present in every `src` file, there is no `env()` outside config, no injectable raw SQL, Filament v4/v5 property typings are correct throughout, and the module architecture (each capability behind a fluent `with*()` opt-in that no-ops until enabled) is genuinely decoupled and clean. The security fundamentals that matter most are done right: OTP codes are CSPRNG-generated and stored only as bcrypt hashes with single-use + per-code attempt burn + `lockForUpdate()` inside a transaction; OAuth keeps CSRF `state` intact and hardens open-redirects; sessions are strictly user-scoped (a user cannot revoke another's session, with a test proving it); analytics masks PII (IP hashing, `array_keys` of credentials only, HMAC'd OTP targets); session fixation is handled with `regenerate()` on every login path.

**All three previously-tracked bugs are RESOLVED** and re-verified against current code: the `AnalyticsPage $routePath` route-eviction (now sets `$routePath='analytics'` with an explanatory comment + test), the `withFilamentTwoFactorChallengePage()` fatal (now registered via `$panel->routes(...)` instead of `pages()`, with a regression test), and `auth.layout => null` (now defaults to `layouts.app` and is read in 7 places with a `?:` fallback + covering test). The device-revoke remember-token cycling + `two_factor_remember_token` migration is implemented well. **Update the memory notes** — those four items are fixed. (Sanctum finite-expiry/rotation is *not* in this package; if that memory referred here, it's still open or lives in host apps.)

What keeps this from an A is a **cluster of auth-flow correctness bugs at the seams** — the pre-2FA login performs a full real login before the second factor (firing new-device emails and cycling remember-tokens for a password-only attacker), a 2FA enrolment redirect loop, several concurrency TOCTOU races (TOTP replay guard, recovery-code consumption, OTP send), config/UI mismatches that can brick OTP verification, and the `'auto'` social-linking mode enabling account-fixation takeover — plus a **completely untested Analytics module** on the hot path and a **dead `visits_daily` rollup pipeline** (an hourly job feeding a table nothing reads).

**Overall health grade: B.** (Architecture A, core crypto/authz A−, seam correctness C+, analytics quality C, tests B but with a module-sized hole.)

### Top 5 Issues

| # | ID | Severity | Summary |
|---|-----|----------|---------|
| 1 | PNB-001 | HIGH | Pre-2FA `Auth::attempt()` fully logs the user in before the second factor → password-only attacker triggers new-device emails, double-counts analytics, and cycles remember-me on all devices |
| 2 | PNB-002 | HIGH | ✅ FIXED (PR #3) — `RequireTwoFactor` enrolment redirect points at the login challenge → infinite redirect loop; unenrolled required-role users can never enrol |
| 3 | PNB-003 | HIGH | ✅ FIXED (PR #3) — `'auto'` social-email linking links a provider-verified email into an unverified local account → account-fixation takeover |
| 4 | PNB-004 | HIGH | ✅ FIXED (PR #3) — `visits_daily` rollup runs hourly but no widget reads it — dead pipeline; ~120 lines live-but-useless |
| 5 | PNB-005 | HIGH | ✅ FIXED (PR #3) — Analytics module (~1,294 lines, PII + hot path) has **zero tests** |

---

## Findings by Severity

### HIGH

**PNB-001 — Pre-2FA login completes a real login before the second factor** — ✅ FIXED 2026-07-08 (PR #3)
`src/Auth/Livewire/Login.php:42-77`. On correct password, `Auth::attempt()` completes a real login (fires `Illuminate\Auth\Events\Login`), then the code stashes the 2FA challenge and calls `Auth::logout()`. Consequences: (a) `DetectNewDeviceLogin` + the analytics `login` event fire on password-only success — an attacker who knows the password but not the TOTP generates "new device sign-in" emails and is recorded as a successful login; (b) `Login` fires **twice** per 2FA login (attempt + final `Auth::login()`), double-counting; (c) `Auth::logout()` cycles `remember_token`, so every 2FA-gated login silently invalidates remember-me on all the user's other devices, and a password-only attacker can trigger this at will.
*Fix:* validate credentials without logging in — `Auth::validate()` + `getProvider()->retrieveByCredentials()` (Fortify's approach) — and only call `Auth::login()` after the 2FA gate passes.

**PNB-002 — 2FA enrolment redirect is a dead-end loop** — ✅ FIXED 2026-07-08 (PR #3, `1fcbc5b`)
`src/TwoFactor/Http/Middleware/RequireTwoFactor.php:85`. Unenrolled users in a required role are redirected to `route('two-factor.challenge')`, whose `mount()` (`TwoFactorChallenge.php:36-39`) bounces to `route('login')` when no pending challenge exists (always true for an already-authenticated unenrolled user). `login` is under `guest` middleware → `RedirectIfAuthenticated` sends them to panel home → middleware again → loop. There is no path to actually enrol (the profile slide-over lives on pages the middleware blocks). The middleware test only asserts *a* redirect happens, so the loop is untested.
*Fix applied:* redirect target is now the configurable `filament-panel-base.two_factor.enrolment_route` (config, `FILAMENT_PANEL_BASE_2FA_ENROLMENT_ROUTE`), which the middleware auto-exempts so it is reachable. When unset or unresolvable the middleware fails open instead of looping (enforcement is opt-in via a reachable enrolment page). New end-to-end tests follow the redirect and assert it lands on the enrolment route (never the challenge) and that the enrolment route passes through without looping.

**PNB-003 — `'auto'` social-email linking enables account-fixation takeover** — ✅ FIXED 2026-07-08 (PR #3)
`src/Auth/Concerns/FindsOrCreatesFromSocialite.php:77`. In `'auto'` mode a provider-verified email links into a local account **without requiring the local account's email to be verified**. Attacker pre-registers `victim@x.com` locally (verification pending) with their own password; victim later "Sign in with Google" → linked into the attacker's account, attacker keeps password access. `'trust_verified'` correctly requires both sides. Default is `require_login` (safe), so severity is conditional on host config.
*Fix applied:* `'auto'` now shares `'trust_verified'`'s linking condition — provider-verified **and** local `email_verified_at !== null` — so a link into a pre-existing account requires both sides to have proven ownership (the attacker can't verify an inbox they don't control). `'auto'` still differs from `'trust_verified'` only on new-user creation trust. Two regression tests: `auto` refuses to link into an unverified local account and links into a verified one.

**PNB-004 — `visits_daily` rollup pipeline is dead (written hourly, read by nothing)** — ✅ FIXED 2026-07-08 (PR #3, delete — Mo's decision)
`RollupAnalyticsCommand` runs hourly (`FilamentPanelBaseServiceProvider:292-294`) populating `visits_daily`, but **every widget queries the raw `visits` table** (`VisitorsChartWidget` even documents this). `visits_daily` is referenced only by the rollup, the prune, and the model. A wasted hourly job + an ever-growing table.
*Fix applied (delete):* removed `RollupAnalyticsCommand`, `VisitDaily`, the `create_visits_daily_table` migration, the hourly schedule + command registration, and the `visits_daily` prune branch; widgets already read raw `visits` and are unaffected. **Kept `retain_aggregated_days`** — the review called it "the `retain_aggregated_days` half" but that setting *also* governs `auth_events` retention (a live feature), so deleting it would have broken auth-event pruning; it now documents only the auth_events window. README/plugin/settings docs updated. Prune retention is now locked in by `PruneAnalyticsCommandTest` (deletes past-window rows, keeps recent, idempotent).

**PNB-005 — Analytics module has zero tests** — ✅ FIXED 2026-07-08 (PR #3)
No `tests/Analytics/` directory. The entire module (~1,294 lines: `VisitWriter`, `IpAnonymizer`, `BotDetector`, `UserAgentParser`, `AuthEventSubscriber`, both commands, 9 widgets, `AnalyticsPage`) is untested despite sitting on the hot request/login path and handling PII masking + retention. Highest-value targets: `IpAnonymizer` (truncate/hash), `BotDetector`, `AuthEventSubscriber` PII masking, `RollupAnalyticsCommand` idempotency, `AnalyticsPage::canAccess()`.
*Progress:* new `tests/Analytics/` now covers every listed target except the rollup — `IpAnonymizerTest.php` (8: null/empty, all three modes, IPv4/IPv6 masking, salt, determinism), `BotDetectorTest.php` (6: empty-UA, every signature case-insensitive, real browser UAs pass), `AuthEventSubscriberTest.php` (5: OTP-target HMAC / no raw PII, context whitelist, enabled+track gates, insert-failure never breaks auth), `UserAgentParserTest.php` (7: device/browser/platform incl. Edge-before-Chrome, empty), `VisitWriterTest.php` (7: field mapping + IP anonymisation, bot flag, duration, both gates, sync insert, missing-table swallow), `AnalyticsPageAccessTest.php` (6: `canAccess()` fails closed for guest/non-admin, custom role, no-role-system, hasRole throw) — **39 tests green**. The rollup was deleted under PNB-004, so its retention path is now the prune command — covered by `PruneAnalyticsCommandTest.php` (2: deletes past-window rows / keeps recent, idempotent). **41 analytics tests total; module now fully covered.**

**PNB-006 — `DemoPage::confirmSeeder()` runs `migrate:fresh` from a web request** — ✅ FIXED 2026-07-08 (PR #3)
`src/Livewire/Demo/DemoPage.php:192-193` — `Artisan::call('migrate:fresh', ['--force'=>true])` + `db:seed` wipes the entire DB from a (password-gated, rate-limited, env-opt-in) Livewire action. Acceptable for a demo box, but it's a full DB-destruction primitive over HTTP.
*Fix applied:* added `demo.allow_reseed` (config + `FILAMENT_PANEL_BASE_DEMO_ALLOW_RESEED`, default false); `confirmSeeder()` now hard-refuses — before touching the DB — when `app()->isProduction()` or the flag is off, setting an error instead. Two regression tests: refuses with the flag off, and refuses in production even with the flag on.

### MEDIUM

- **PNB-007 — TOTP replay guard TOCTOU** (`src/TwoFactor/Services/TwoFactorAuthenticator.php:64-86`): `Cache::get`→verify→`Cache::put` isn't atomic; two parallel submits of the same code both pass. Use `Cache::lock` or atomic `Cache::add` on `sha1($secret.$code)`. Also a no-op on `CACHE_DRIVER=array` — doc it.
- **PNB-008 — Recovery-code consumption race** (`HasTwoFactorAuthentication.php:234-265`): read-modify-write of the hashed list with no lock → same code yields two logins. Consume in `DB::transaction` + `lockForUpdate()`.
- **PNB-009 — Remember-device cookie not device-bound, no server-side expiry** (`TwoFactorChallengeSession.php:123-138`): token is `HMAC(userId|secret|nonce)` — identical per device, no timestamp; a single exfiltrated value trusts all future logins until secret/nonce rotation. Embed issue-time + reject server-side; add a per-device random component.
- **PNB-010 — Password reset/change doesn't invalidate 2FA remember-device trust** (`ResetPassword.php:52-59`, `HasProfileSlideOver.php:168`): rotates `remember_token` but not `two_factor_remember_token`. Post-reset, an attacker holding the remember-device cookie still skips 2FA. Rotate `two_factor_remember_token` in the reset closure + profile change.
- **PNB-011 — Throttle-key bypass via identifier case/whitespace** (`ThrottlesAuthAttempts.php:110-115`, `Login.php:38-43`): raw identifier HMAC'd, so `Victim@x.com`/`VICTIM@x.com ` hit distinct per-identifier buckets against the same DB row — defeats the distributed-attack bucket. `mb_strtolower(trim())` in `rateKey()`; same gap in VerifyOtp/ForgotPassword/ResetPassword.
- **PNB-012 — `otp_code_length` UI max (10) vs generator clamp (8) bricks verification** (`ManageAuthenticationSettings.php:197` allows `maxValue(10)`; `OtpService.php:149` clamps to 8; `VerifyOtp.php:32` validates `size:length`). Admin sets 9/10 → no OTP can ever verify. Set `->maxValue(8)`.
- **PNB-013 — Email never lowercased at registration/social match** (`Register.php:66`, `RegistrationRules.php:44`, `FindsOrCreatesFromSocialite.php:67`): on PostgreSQL, `Foo@x.com`/`foo@x.com` become two accounts and social sign-in creates duplicates. Lowercase once at intake.
- **PNB-014 — Phone login can never match locally-typed numbers** (`Login.php:140-147` uses identifier verbatim while `Register.php:138-148` stores E.164): a user who registered via country-code dropdown and logs in typing `0791234567` fails forever. Apply `normalisePhone()` in the Login phone branch.
- **PNB-015 — Dead config throttle/OTP keys** (`config/filament-panel-base.php:491-494`, `:474` never read; `OtpDriverManager.php:31` reads non-existent `auth.otp.default`): operators tuning config throttles see no effect. Wire config as the Settings-class fallbacks or delete + add the missing key.
- **PNB-016 — `RegistrationPipeline` force-injects `status` for every host** (`RegistrationPipeline.php:97-105`): hosts without a `status` column get `MassAssignmentException` under `shouldBeStrict()`. Gate on `is_a($userModel, HasModerationStatus::class, true)` like the socialite trait already does.
- **PNB-017 — Email-channel OTP marks *phone* verified** (`VerifyOtp.php:54-58`): stamps `phone_verified_at` even when `otp_driver='email'`. Only mark phone verified when the resolved target was the phone.
- **PNB-018 — Password rules below fleet baseline** (`RegistrationRules.php:29`, `ResetPassword.php:41` use bare `min:8`): no `Password::defaults()`, no `uncompromised()` on the shared backbone. Adopt `Password::defaults()`.
- **PNB-019 — Missing-package failure swallowed at challenge time** (`TwoFactorAuthenticator.php:87-89` `catch(\Throwable){return false;}`): also swallows the deliberate `RuntimeException` from a missing `pragmarx/google2fa` (only a `suggest:`) or an `APP_KEY` rotation — every 2FA user locked out with a misleading "invalid code" and no logging. Catch only Google2FA exception types; `report()` before returning false.
- **PNB-020 — Bundled fallback auth layout ships zero CSS** (`resources/views/layouts/auth.blade.php`): `@livewireStyles`/`@livewireScripts` only, no `@vite`/Tailwind, so the "minimal fallback" renders unstyled raw HTML. Inline a minimal stylesheet.
- **PNB-021 — Bulk-safe query gaps / role escalation surface in `UserResource`** (`UserResource.php:188-194`): Roles `Select` has no allowlist, so every role incl. `super_admin` is assignable; safe by default (super-admin-only access) but a host granting a non-super-admin access via `withUserManagement(authorize:...)` lets them self-assign `super_admin`. Offer an `assignableRoles` filter.
- **PNB-022 — Command palette leaks inaccessible resources** — ✅ FIXED 2026-07-08 (PR #3) — (`FilamentNavigationContributor.php:30-65`): emits "Go to …" entries without `canAccess()` checks; low-priv users see every resource/page name+URL. Skip entries where `canAccess()` is false.
- **PNB-023 — Package blades use non-safe Tailwind colors** (fleet rule: only `primary`+`gray` compile reliably): `warning/danger/success/info` in `two-factor/profile-tab.blade.php`, `partials/recovery-codes.blade.php`, `two-factor-challenge.blade.php` (`text-red-600`), `analytics/widgets/slowest-pages.blade.php`, plus widespread `dark:bg-white/5`/`white/10` across command-palette/sessions/two-factor, and `green-*`/`red-*` in the auth views (login/register/forgot/reset/verify). Sibling commit `7d14475` already fixed exactly this in `device-session-list.blade.php` — sweep the rest to primary/gray-safe.
  - **✅ FIXED 2026-07-08 (PR #3).** Removed the semi-transparent surface tints (`dark:bg-white/5`, `dark:bg-white/10`, `dark:border-white/10`, `dark:divide-white/10`, `dark:ring-white/10`, and `dark:bg-*-900/NN` opacity) → solid `gray`/`primary`/semantic-solid across the in-panel blades: `two-factor/{profile-tab,partials/recovery-codes}`, `command-palette/{body,modal}`, `analytics/widgets/{slowest-pages,geo-breakdown,top-pages}`, `sessions/device-session-list`. Per [[feedback_no_semitransparent_ui]] (a standing directive, no visual-identity decision). **Kept** all semantic/brand hues incl. the `slowest-pages` latency traffic-light (`danger/warning/success` — a severity signal that DOES compile in Filament apps, which register those colors by default) and the modal scrim (`bg-black/40` overlay). Full suite 370 green. **NOT touched (correctly):** `demo/page.blade.php` (CDN Tailwind), host-layout auth views (`red/green` compile there + error-red is correct UX), `panel-badge`/phone/country components. The review's "sweep the rest to primary/gray" was **overbroad** — see scope note below.
  - Scope audit (why the sweep was narrowed): all 26 blades with color classes (81 occurrences). `livewire/demo/page.blade.php` (16 hits) deliberately loads Tailwind **CDN** in its self-contained layout, so every color compiles there — must NOT be touched; the **auth views** (login/register/forgot/reset/verify, two-factor-challenge) render in the **host app's layout** with the host Tailwind build, where `red/green` compile AND error-red is correct UX — changing them to gray would *harm* usability; `panel-badge.blade.php` + phone/country components are semantic/host-context. The genuinely in-scope set (rendered in-panel with the package's compiled theme) is small: `two-factor/profile-tab`, `two-factor/partials/recovery-codes`, `command-palette/{body,modal}`, `analytics/widgets/{slowest-pages,geo-breakdown,top-pages}`, and the 1 remaining `device-session-list` line. Apply the `7d14475` pattern (success→`primary`, danger/warning→`gray`, `/NN` opacity→solid) to **that subset only**, as a **visual show-before-commit** pass (MEDIUM, non-blocking per S6). Not auto-swept to avoid visual regressions.

### LOW

- **PNB-024** Plaintext-secret fallback on decrypt failure (`HasTwoFactorAuthentication.php:48-52,75-79`) silently accepts unencrypted secrets and masks APP_KEY-rotation breakage. Log once on fallback.
- **PNB-025** `logoutOtherDevices`/`revoke` need no password re-confirmation (`DeviceSessionList.php:34-95`) — only `wire:confirm`. Add `->currentPassword()` like the 2FA-disable action.
- **PNB-026** `DeviceSessionList` actions unguarded against the repo's `RuntimeException` (`render()` catches at `:147`, actions don't) → raw Livewire 500 if the driver flips mid-session.
- **PNB-027** Session listing selects `*` incl. multi-KB `payload` (`DeviceSessionRepository.php:41-44`); select only needed columns. `detectUserIdColumn()` runs `Schema::hasColumn` per call — memoize.
- **PNB-028** `DetectNewDeviceLogin` hardcodes `user_id` (`:64`) while the repo has a `detectUserIdColumn()` fallback — legacy-schema hosts silently get no new-device events; also loads all sessions and hashes in PHP.
- **PNB-029** Route-existence assumptions (`RequireTwoFactor.php:85`, `TwoFactorChallenge.php:38,89` assume `two-factor.challenge`/`login`/`home`): hosts with `auth.routes.enabled=false` get `RouteNotFoundException`. Add `Route::has()` guards + config fallback URLs.
- **PNB-030** OTP `send()` delete+insert not transactional (`OtpService.php:51-66`) → concurrent sends race the unique index into a 500. `DB::transaction`/`updateOrInsert`.
- **PNB-031** OTP existence timing oracle (`OtpService.php:95-104`): early `return false` vs `Hash::check` reveals whether an active OTP exists. Dummy `Hash::check` on the miss path.
- **PNB-032** `OtpService.php:96-99` — null `$userId` query doesn't add `whereNull('user_id')`, so a user-bound code can be consumed by a caller omitting the id. Add the guard.
- **PNB-033** OAuth `userFromCallback()` invoked twice (`OAuthController.php:85,134`) — only works via Socialite's internal cache; pass the resolved `$socialUser` into `handle()`.
- **PNB-034** SMS/WhatsApp OTP drivers have no `->timeout()` (`TwilioSmsOtpDriver.php:47-57` et al.) — a slow provider holds the request 30s; they also log full `target` PII at warning/error.
- **PNB-035** `ManageAppearanceSettings::canAccess()` fails open (`:60` falls back to `true` when the User lacks `isSuperAdmin()`), weaker than every other page. Mirror the `hasRole(config('admin_role'))` cascade.
- **PNB-036** OTP notification serializes the cleartext code into the queue payload (`OtpCodeNotification.php`); DB-queue dumps expose live codes for their TTL.
- **PNB-037** `throttle` hit before validation in `Register.php:58` — two-typo users hit lockout with `throttle_per_minute=5`.

---

## Dead Code & Simplification (~150–180 removable lines)

- **`visits_daily` pipeline (~120 L)** — `RollupAnalyticsCommand` (95), `VisitDaily` (27), migration, hourly schedule, `retain_aggregated_days` half — dead (PNB-004). Wire up or delete.
- **`RegistrationSettings`** — `@deprecated since 2.0` but still shipped/registered; flag, migrate hosts before removal.
- **`AuthEvent::TYPE_PASSWORD_RESET`** constant defined (`AuthEvent.php:32`) but never emitted.
- **`DeviceSessionList::$confirmingRevoke` / `$sessionToRevoke`** (`:30-32`) never referenced (superseded by `wire:confirm`).
- Doc drift: `generateSecret()` docblock "26 chars/130 bits" vs actual `generateSecretKey(32)`/160 bits; `RequireTwoFactor` docblock says `hasRole()` but code uses `hasAnyRole()`.

---

## Performance

- Per-request country-code DB lookup (`VisitWriter::resolveCountryCode()` `:142`, `AuthEventSubscriber::resolveCountryCode()` `:272`) — `find($id)` on every visit/auth write. Cache the id→code map or store the code in the session in `SetCountry`.
- `VisitorsChartWidget::hourlyData()` `->get(['created_at'])` groups in PHP (bounded to 24h, acceptable).
- Done well: `TrackVisit` runs in `terminate()` off the response path; `RecordVisitJob` queueable; `PruneAnalyticsCommand` chunk-deletes; good composite indexes on `visits`; nav-badge counts `Cache::remember`'d 300s.

## Design (strong — largely leave alone)

The fluent module API (`withAnalytics`/`withCommandPalette`/`withAuthentication`/`withTwoFactor`/`withSessionManagement`/`withUserManagement`/`withAppearanceSettings`) is cohesive and genuinely decoupled; opt-ins no-op until enabled; override points are thorough (publishable views/migrations/theme, swappable models, `settingsUsing()`, host subclass pages, static closure hooks on `UserResource` kept serializable for `config:cache`). The two large files (provider 686 L, plugin 731 L) are cohesive — no action needed.

---

## Test Gaps (ranked)

1. **Entire Analytics module** (PNB-005) — start with `IpAnonymizer`, `BotDetector`, `AuthEventSubscriber` PII masking, rollup idempotency, `AnalyticsPage::canAccess()`.
2. **`TwoFactorChallenge::submit` Livewire e2e** — success login+regenerate, failure event+throttle, recovery path, remember-device issuance, stale-session bounce (would surface PNB-001).
3. **`DeviceSessionList` Livewire** — revoke, logout-others, credential-rotation re-establishment.
4. **Replay-guard + parallel recovery-code consumption** (would surface PNB-007/008).
5. **`RequireTwoFactor` end-to-end** (would surface the PNB-002 loop).
6. **`'auto'` social-linking policy** (PNB-003), **OTP length mismatch** (PNB-012), **duplicate `send()` concurrency** (PNB-030), **OAuth callback honoring 2FA**.
7. **`UserResource`** — `is_protected` deletion guard and password-optional-on-edit dehydration.

**Done well (do not re-flag):** encrypted-at-rest 2FA secrets + OAuth tokens; bcrypt single-use recovery codes; TOTP replay protection existing at all; three-bucket throttling with HMAC'd keys and a deliberately-not-cleared day bucket; session fixation `regenerate()` on all login paths; OAuth CSRF `state` intact + open-redirect hardening + provider-id-first matching + cross-user link rejection; strictly user-scoped session queries with a cross-user-revoke refusal test; PII masking across analytics; enumeration-safe forgot-password; signed email-verify routes; disposable-email/domain-allowlist offline rules. All three tracked bugs verified fixed.

---

## Added-Value Roadmap

1. **Passkeys / WebAuthn** as a first-class second factor (and passwordless primary) — the natural next step beyond TOTP; slots into the existing challenge/remember-device infra.
2. **Passwordless magic-link** (already on the fleet roadmap) — hook at the Login component alongside the OTP driver abstraction; reuse the signed-route + throttle machinery.
3. **Risk-based login alerts** — extend `DetectNewDeviceLogin` into impossible-travel / new-country alerts using the analytics geo data already collected.
4. **Admin impersonation with audit trail** — a common fleet need; gate behind super-admin + record start/stop in the analytics `auth_events` stream.
5. **Step-up authentication** — require 2FA re-confirmation for sensitive actions (device revoke-all, password change, role grants) — directly fixes PNB-025.
6. **Login analytics dashboard** — surface the (currently under-used) auth-event data: failed-login heatmap, 2FA adoption rate, OAuth-vs-password mix.
7. **Wire up `visits_daily`** into long-range widgets so the retention/rollup design pays off (also resolves PNB-004).

---

## Execution Plan for Claude Opus 4.8

> Windows + Herd machine. **Run all PHP via the PowerShell tool, never Bash** (`php vendor/bin/pest`, `php vendor/bin/pint --dirty`). This package is the backbone for ~18 apps — **migrations must be additive (new files, never edits to shipped ones); do not break the public plugin API or config key names without a deprecation path.** Review is read-only; below is the prescribed work.

### Phase 0 — Auth-flow correctness (P0, highest user impact)

- **0.1 (PNB-001)** `src/Auth/Livewire/Login.php:42-77` — replace the `attempt()`+`logout()` dance with credential validation that never logs in (`Auth::validate()` / `retrieveByCredentials()`); only call `Auth::login()` after the 2FA gate. Verify with a new Livewire test asserting no `Login` event / no new-device email fires on a password-only (2FA-pending) submission. `php vendor/bin/pest --filter=Login` via PowerShell.
- **0.2 (PNB-002)** `RequireTwoFactor.php:85` — redirect unenrolled required-role users to a dedicated configurable enrolment route (not the challenge). Add an end-to-end middleware test that follows redirects and asserts no loop + the enrolment page is reachable.
- **0.3 (PNB-012)** `ManageAuthenticationSettings.php:197` — `->maxValue(8)`; add a test that generating + verifying at each allowed length round-trips.
- **0.4 (PNB-014, PNB-013)** `Login.php:140-147` — apply `normalisePhone()` in the phone branch; lowercase email once at intake in `Register.php:66` + `FindsOrCreatesFromSocialite.php:67`. Tests: local-format phone login succeeds; mixed-case email matches one account.

### Phase 1 — Concurrency & 2FA hardening (P0/P1)

- **1.1 (PNB-007)** `TwoFactorAuthenticator.php:64-86` — atomic replay guard via `Cache::lock` or `Cache::add`. Test: same code submitted twice (second must fail).
- **1.2 (PNB-008)** `HasTwoFactorAuthentication.php:234-265` — consume recovery code in `DB::transaction`+`lockForUpdate`. Test: parallel consume of one code yields exactly one success.
- **1.3 (PNB-009, PNB-010)** Embed issue-time in the remember-device token and reject server-side; rotate `two_factor_remember_token` on password reset (`ResetPassword.php`) and profile change (`HasProfileSlideOver.php`). Tests: expired token rejected; post-reset cookie no longer skips 2FA.
- **1.4 (PNB-019)** `TwoFactorAuthenticator.php:87-89` — narrow the catch to Google2FA exceptions; `report()` on others.
- **1.5 (PNB-003)** `FindsOrCreatesFromSocialite.php:77` — require local `email_verified_at` in `'auto'` mode; test the takeover scenario is blocked.

### Phase 2 — Throttle, OTP, registration edges (P1)

- **2.1 (PNB-011)** Normalize (`mb_strtolower(trim())`) identifiers inside `rateKey()` across Login/VerifyOtp/ForgotPassword/ResetPassword.
- **2.2 (PNB-015)** Wire the dead `auth.throttle.*`/`auth.otp.*` config keys into the Settings-class fallbacks (or delete + add `auth.otp.default`).
- **2.3 (PNB-016)** `RegistrationPipeline.php:97-105` — inject `status` only when the User implements `HasModerationStatus`.
- **2.4 (PNB-017)** `VerifyOtp.php:54-58` — mark phone verified only for phone-channel OTPs.
- **2.5 (PNB-018)** Adopt `Password::defaults()`+`uncompromised()` in registration/reset rules.
- **2.6 (PNB-030, PNB-031, PNB-032)** Transactional OTP `send()`; dummy `Hash::check` on miss; `whereNull('user_id')` when unbound.
- **2.7 (PNB-034)** Add `->timeout()` to SMS/WhatsApp drivers; stop logging full `target`.

### Phase 3 — Analytics: wire-up, tests, perf (P1)

- **3.1 (PNB-004)** Decide: wire `visits_daily` into range>24h widgets, **or** delete the rollup pipeline (command/model/migration/schedule/setting). Recommend wiring up (see roadmap #7).
- **3.2 (PNB-005)** Add `tests/Analytics/` covering `IpAnonymizer`, `BotDetector`, `AuthEventSubscriber` PII masking, rollup idempotency, `AnalyticsPage::canAccess()`.
- **3.3 (perf)** Cache the country id→code map (or store code in session in `SetCountry`).

### Phase 4 — Access-surface & demo hardening (P1/P2)

- **4.1 (PNB-006)** `DemoPage::confirmSeeder()` — add `demo.allow_reseed` (default false) + hard `app()->isProduction()` refusal.
- **4.2 (PNB-022)** Filter command-palette navigation entries by `canAccess()`.
- **4.3 (PNB-021)** `UserResource` — add an `assignableRoles` filter; block granting `super_admin` unless the actor is a super-admin.
- **4.4 (PNB-035)** Fix `ManageAppearanceSettings::canAccess()` fail-open.
- **4.5 (PNB-025)** Add `->currentPassword()` to `logoutOtherDevices`/`revoke`.

### Phase 5 — Standards & cleanup (P2)

- **5.1 (PNB-023)** Sweep package blades to primary/gray-safe colors (mirror commit `7d14475`); swap `white/10` → `gray-*`.
- **5.2** Remove dead code: `visits_daily` (if 3.1 chose delete), `DeviceSessionList` unused props, `TYPE_PASSWORD_RESET` (or emit it). Fix docblock drift.
- **5.3 (PNB-020)** Inline a minimal stylesheet into the bundled fallback auth layout.
- **5.4 (PNB-027, PNB-028)** Trim session `select`, memoize `detectUserIdColumn()`, use it consistently in `DetectNewDeviceLogin`.

### Do NOT do
- Do **not** edit shipped migrations — add new additive ones only.
- Do **not** rename or remove public config keys / plugin methods without a deprecation path — ~18 apps depend on them.
- Do **not** weaken the verified-correct controls: encrypted secrets/tokens, single-use bcrypt recovery codes, three-bucket throttling, session fixation regeneration, user-scoped session queries, OAuth CSRF `state`/open-redirect hardening, PII masking.
- Do **not** re-introduce the three fixed bugs (`$routePath`, `withFilamentTwoFactorChallengePage()`, `auth.layout`) — and update the fleet memory notes to mark them resolved.
- Do **not** run tests without approval per fleet policy; the verification steps above are for when approval is given.
- No unrelated refactors; every changed line must trace to a finding.

## Verification Pass (Fable, adversarial, 2026-07-06)

Independent source-level re-check of every HIGH finding plus the three "fixed" claims. Compiler wrote the review blind from sub-agent summaries; every citation below was opened and read directly.

- **PNB-001 — CONFIRMED, HIGH.** `src/Auth/Livewire/Login.php:42` really is `Auth::attempt([...], $this->remember)` (full login → fires `Illuminate\Auth\Events\Login`), then `:70-72` stashes the challenge and calls `Auth::logout()`. `DetectNewDeviceLogin` is registered on `Login` at `FilamentPanelBaseServiceProvider.php:352-353` and `AuthEventSubscriber.php:46` also listens on `Login` — both fire on password-only success. `TwoFactorChallenge.php:82` calls `Auth::login()` again on success → `Login` fires twice per 2FA login. `SessionGuard::logout()` cycles a non-empty `remember_token`, so remember-me on other devices is invalidated by any password-only attempt. Every claimed consequence checks out.
- **PNB-002 — CONFIRMED, HIGH** (loop is host-routing-dependent; dead-end is unconditional). `RequireTwoFactor.php:85` redirects unenrolled required-role users to `route('two-factor.challenge')`; `TwoFactorChallenge.php:37-38` bounces to `route('login')` when `!$challenge->hasPending()` (always true for a fully-authenticated unenrolled user); `routes/auth.php:31-33` puts `login` under `guest` middleware → `RedirectIfAuthenticated` → home. If home is behind the middleware (panel), it's an infinite loop; if home is public, the user is permanently locked out of the panel with no enrolment path. Either way the finding stands.
- **PNB-003 — CONFIRMED, but downgrade HIGH → MEDIUM (conditional).** `FindsOrCreatesFromSocialite.php:72-81`: `'auto'` requires the *provider* email to be verified (`:77`) but never checks local `email_verified_at` — only `'trust_verified'` does (`:78-79`). The pre-registration fixation scenario is real as written. Downgrade rationale: reachable only when a host opts into a non-default mode the code itself documents as "legacy, unsafe" (`:30`); default `require_login` returns null (`:80-91`). Fix recommendation stands.
- **PNB-004 — CONFIRMED, but severity is inflated: HIGH → LOW (dead code, no security/correctness impact).** `visits_daily` / `VisitDaily` referenced only by `RollupAnalyticsCommand.php:48,84`, `PruneAnalyticsCommand.php:45`, the model, migration, and README; grep of all 9 widgets shows every one queries `Visit::query()` (raw `visits`); `VisitorsChartWidget.php:16` explicitly documents reading raw. Finding factually correct; a wasted hourly job is not HIGH.
- **PNB-005 — CONFIRMED, HIGH.** `tests/` contains no `Analytics/` directory (dirs: Appearance, Auth, CommandPalette, Sessions, Support, TwoFactor, UserManagement + root files); grep for `Analytics|IpAnonymizer|BotDetector|VisitWriter` across `tests/` hits only two Sessions tests (incidental). Zero analytics coverage on a PII-handling hot path — severity holds.
- **PNB-006 — CONFIRMED, but downgrade HIGH → MEDIUM.** `DemoPage.php:192-193` runs `Artisan::call('migrate:fresh', ['--force'=>true])` + `db:seed` from a Livewire action. Mitigations verified: routes only register when `demo.enabled` (default false, `FilamentPanelBaseServiceProvider.php:469`), password gate uses `hash_equals` + rate limiting (`DemoPage.php:156-173`). No `isProduction()` guard exists — the recommended hardening is valid — but a default-off, password-gated, throttled opt-in demo primitive is MEDIUM, not HIGH.

**"Fixed" claims spot-verified:**
- `$routePath` route-eviction: **CONFIRMED FIXED** — `AnalyticsPage.php:38` `protected static string $routePath = 'analytics';` with the explanatory comment at `:35-37`.
- `withFilamentTwoFactorChallengePage()` fatal: **CONFIRMED FIXED** — `FilamentPanelBasePlugin.php:687-691` registers the SimplePage via `$panel->routes(...)`, not `pages()`, with a comment explaining the `registerRoutes` fatal.
- `auth.layout => null`: **CONFIRMED FIXED** — `config/filament-panel-base.php:443` defaults to `'layouts.app'`; consumers use `?: 'filament-panel-base::layouts.auth'` fallback (e.g. `Login.php:136`).

**Assessment of the blind compile:** all six HIGH findings are factually accurate at the cited file:line — no fabrications found. Two severities were inflated (PNB-004, PNB-006) and one is config-conditional (PNB-003).

**Tally:** 6/6 HIGH findings CONFIRMED on facts; 0 REFUTED; 3 severity corrections (PNB-003 HIGH→MEDIUM, PNB-004 HIGH→LOW, PNB-006 HIGH→MEDIUM); 3/3 "fixed" claims verified genuinely fixed.
