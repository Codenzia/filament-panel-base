# filament-panel-base — Feature Surfaces

Inventory of the user-facing surfaces this package ships, and where each is
covered by the test suite. The **BVT** column points at the thin existence net
under `tests/BVT/` (mounts / class + view roll-call); the **Deep** column points
at the behaviour suites.

## Authentication (standalone Livewire)

| Surface | Class | BVT | Deep |
| --- | --- | --- | --- |
| Login | `Auth\Livewire\Login` | roll-call + mount | `tests/Auth/*` |
| Register | `Auth\Livewire\Register` | roll-call + mount | `tests/Auth/Register*` |
| Forgot password | `Auth\Livewire\ForgotPassword` | roll-call + mount | `tests/Auth/*` |
| Reset password | `Auth\Livewire\ResetPassword` | roll-call | `tests/Auth/*` |
| Verify email notice | `Auth\Livewire\VerifyEmailNotice` | roll-call | `tests/Auth/*` |
| Verify OTP | `Auth\Livewire\VerifyOtp` | roll-call | `tests/Auth/VerifyOtpChannelTest`, `OtpServiceTest` |
| Manage social accounts | `Auth\Livewire\ManageSocialAccounts` | roll-call | `tests/Auth/OAuthFlowTest` |
| Twilio SMS OTP transport (via codenzia/laravel-sms) | `Auth\Drivers\Otp\TwilioSmsOtpDriver` | — | `tests/Auth/OtpDriversTest` |
| Headless OTP REST API (opt-in) | `Auth\Http\Controllers\Api\{Otp,Phone}Controller` | — | `tests/OtpApi/OtpApiTest`, `tests/Auth/OtpApiDisabledTest` |
| OIDC single sign-on (opt-in) | `Sso\Http\Controllers\SsoController` | — | `tests/Sso/SsoFlowTest`, `tests/Sso/SsoDisabledTest` |
| SSO login buttons (opt-in) | `filament-panel-base::sso.buttons` | — | `tests/Sso/SsoFlowTest`, `tests/Sso/SsoDisabledTest` |

## Two-factor & sessions

| Surface | Class | BVT | Deep |
| --- | --- | --- | --- |
| 2FA challenge | `TwoFactor\Livewire\TwoFactorChallenge` | roll-call | `tests/TwoFactor/*` |
| 2FA challenge page | `TwoFactor\Filament\Pages\TwoFactorChallengePage` | roll-call | `tests/TwoFactor/TwoFactorChallengePageTest` |
| Device / session list | `Sessions\Livewire\DeviceSessionList` | roll-call | `tests/Sessions/*` |

## Command palette, demo & admin pages

| Surface | Class | BVT | Deep |
| --- | --- | --- | --- |
| Command palette | `CommandPalette\Livewire\CommandPalette` | roll-call | `tests/CommandPalette/*` |
| Demo page | `Livewire\Demo\DemoPage` | roll-call | `tests/DemoPageGateTest` |
| Auth settings page | `Auth\Filament\Pages\ManageAuthenticationSettings` | roll-call | `tests/Auth/ManageAuthenticationSettingsTest` |
| Appearance settings page | `Filament\Pages\ManageAppearanceSettings` | roll-call | `tests/Appearance/*` |
| Demo settings page | `Filament\Pages\ManageDemoSettings` | roll-call | `tests/DemoSettingModelTest` |
| In-panel Login/Register pages | `Auth\Filament\Pages\{Login,Register}` | roll-call | `tests/Auth/FilamentAuthPagesTest` |
| Notification preferences page (opt-in) | `NotificationMatrix\Filament\Pages\ManageNotificationPreferences` | roll-call | `tests/NotificationMatrix/*` |

## Analytics

| Surface | Class | BVT | Deep |
| --- | --- | --- | --- |
| Analytics page | `Analytics\Filament\Pages\AnalyticsPage` | roll-call | `tests/Analytics/AnalyticsPageAccessTest` |
| 9 analytics widgets | `Analytics\Filament\Widgets\*` | roll-call | `tests/Analytics/*` |

## BVT scope

The BVT layer (`tests/BVT/`) is the thin **build-verification** net: every
shipped Livewire component, Filament page and analytics widget must load (no
autoload/type fatal) and expose a resolvable view; the guest-safe auth
components additionally mount clean. It intentionally does **not** duplicate the
deep behaviour suites — surfaces that need a booted panel or per-request state
are covered by class/view roll-call only.
