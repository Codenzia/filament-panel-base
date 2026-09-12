<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Filament\Pages\AnalyticsPage;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\AuthFunnelWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\DeviceTypeWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\ErrorRateSparklineWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\FailedLoginsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\GeoBreakdownWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\SlowestPagesWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\TopPagesWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\VisitorsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\VisitorsTodayWidget;
use Codenzia\FilamentPanelBase\Auth\Contracts\OtpTokenIssuer;
use Codenzia\FilamentPanelBase\Auth\Filament\Pages\Login as FilamentLogin;
use Codenzia\FilamentPanelBase\Auth\Filament\Pages\ManageAuthenticationSettings;
use Codenzia\FilamentPanelBase\Auth\Filament\Pages\Register as FilamentRegister;
use Codenzia\FilamentPanelBase\Auth\Http\Controllers\Api\OtpController;
use Codenzia\FilamentPanelBase\Auth\Http\Controllers\Api\PhoneController;
use Codenzia\FilamentPanelBase\Auth\Http\Requests\RegisterPhoneRequest;
use Codenzia\FilamentPanelBase\Auth\Http\Requests\RequestOtpRequest;
use Codenzia\FilamentPanelBase\Auth\Http\Requests\VerifyOtpRequest;
use Codenzia\FilamentPanelBase\Auth\Livewire\ForgotPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\Login;
use Codenzia\FilamentPanelBase\Auth\Livewire\ManageSocialAccounts;
use Codenzia\FilamentPanelBase\Auth\Livewire\Register;
use Codenzia\FilamentPanelBase\Auth\Livewire\ResetPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyEmailNotice;
use Codenzia\FilamentPanelBase\Auth\Livewire\VerifyOtp;
use Codenzia\FilamentPanelBase\CommandPalette\Livewire\CommandPalette;
use Codenzia\FilamentPanelBase\Filament\Pages\ManageAppearanceSettings;
use Codenzia\FilamentPanelBase\Filament\Pages\ManageDemoSettings;
use Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage;
use Codenzia\FilamentPanelBase\NotificationMatrix\Filament\Pages\ManageNotificationPreferences;
use Codenzia\FilamentPanelBase\Sessions\Livewire\DeviceSessionList;
use Codenzia\FilamentPanelBase\TwoFactor\Filament\Pages\TwoFactorChallengePage;
use Codenzia\FilamentPanelBase\TwoFactor\Livewire\TwoFactorChallenge;
use Filament\Pages\BasePage as FilamentPage;
use Filament\Widgets\Widget as FilamentWidget;
use Livewire\Component as LivewireComponent;

/**
 * BVT — Build Verification Test (existence net).
 *
 * A thin roll-call over every shipped Livewire component, Filament page and
 * analytics widget: the class must load (no autoload/type fatals) and extend
 * the base type it claims. Deep behaviour lives in the surface-specific suites
 * under tests/Auth, tests/Analytics, tests/Sessions, etc. — this file only
 * guarantees the surface still exists and boots.
 */
it('every shipped standalone Livewire component loads and is a Livewire component', function (string $class): void {
    expect(class_exists($class))->toBeTrue()
        ->and(is_subclass_of($class, LivewireComponent::class))->toBeTrue();
})->with([
    'Auth\Login' => [Login::class],
    'Auth\Register' => [Register::class],
    'Auth\ForgotPassword' => [ForgotPassword::class],
    'Auth\ResetPassword' => [ResetPassword::class],
    'Auth\VerifyEmailNotice' => [VerifyEmailNotice::class],
    'Auth\VerifyOtp' => [VerifyOtp::class],
    'Auth\ManageSocialAccounts' => [ManageSocialAccounts::class],
    'TwoFactorChallenge' => [TwoFactorChallenge::class],
    'CommandPalette' => [CommandPalette::class],
    'DeviceSessionList' => [DeviceSessionList::class],
    'DemoPage' => [DemoPage::class],
]);

it('every shipped Filament page loads, is a Filament page, and its view resolves', function (string $class): void {
    expect(class_exists($class))->toBeTrue()
        ->and(is_subclass_of($class, FilamentPage::class))->toBeTrue();

    $view = (new ReflectionClass($class))->getProperty('view')->getDefaultValue();

    expect($view)->toBeString();

    // Only package-owned views are our responsibility to ship; pages that
    // inherit a Filament-owned view (e.g. the Dashboard-based AnalyticsPage)
    // rely on Filament's own namespace, which isn't registered without a panel.
    if (str_starts_with($view, 'filament-panel-base::')) {
        expect(view()->exists($view))->toBeTrue();
    }
})->with([
    'Auth\Login' => [FilamentLogin::class],
    'Auth\Register' => [FilamentRegister::class],
    'ManageAuthenticationSettings' => [ManageAuthenticationSettings::class],
    'ManageAppearanceSettings' => [ManageAppearanceSettings::class],
    'ManageDemoSettings' => [ManageDemoSettings::class],
    'ManageNotificationPreferences' => [ManageNotificationPreferences::class],
    'TwoFactorChallengePage' => [TwoFactorChallengePage::class],
    'AnalyticsPage' => [AnalyticsPage::class],
]);

it('every analytics widget loads and is a Filament widget', function (string $class): void {
    expect(class_exists($class))->toBeTrue()
        ->and(is_subclass_of($class, FilamentWidget::class))->toBeTrue();
})->with([
    'AuthFunnelWidget' => [AuthFunnelWidget::class],
    'DeviceTypeWidget' => [DeviceTypeWidget::class],
    'ErrorRateSparklineWidget' => [ErrorRateSparklineWidget::class],
    'FailedLoginsChartWidget' => [FailedLoginsChartWidget::class],
    'GeoBreakdownWidget' => [GeoBreakdownWidget::class],
    'SlowestPagesWidget' => [SlowestPagesWidget::class],
    'TopPagesWidget' => [TopPagesWidget::class],
    'VisitorsChartWidget' => [VisitorsChartWidget::class],
    'VisitorsTodayWidget' => [VisitorsTodayWidget::class],
]);

it('every headless OTP API surface class loads', function (string $class): void {
    expect(class_exists($class) || interface_exists($class))->toBeTrue();
})->with([
    'OtpController' => [OtpController::class],
    'PhoneController' => [PhoneController::class],
    'RequestOtpRequest' => [RequestOtpRequest::class],
    'VerifyOtpRequest' => [VerifyOtpRequest::class],
    'RegisterPhoneRequest' => [RegisterPhoneRequest::class],
    'OtpTokenIssuer' => [OtpTokenIssuer::class],
]);

it('every shipped Livewire/Blade view namespace path resolves', function (string $view): void {
    expect(view()->exists($view))->toBeTrue();
})->with([
    // Livewire component views
    'filament-panel-base::livewire.auth.login',
    'filament-panel-base::livewire.auth.register',
    'filament-panel-base::livewire.auth.forgot-password',
    'filament-panel-base::livewire.auth.reset-password',
    'filament-panel-base::livewire.auth.verify-email-notice',
    'filament-panel-base::livewire.auth.verify-otp',
    'filament-panel-base::livewire.auth.manage-social-accounts',
    'filament-panel-base::livewire.auth.two-factor-challenge',
    'filament-panel-base::livewire.command-palette.body',
    'filament-panel-base::livewire.sessions.device-session-list',
    'filament-panel-base::livewire.demo.page',
    // Shared Blade components (anonymous)
    'filament-panel-base::components.auth-links',
    'filament-panel-base::components.country-select',
    'filament-panel-base::components.country-switcher',
    'filament-panel-base::components.currency-switcher',
    'filament-panel-base::components.dark-mode-toggle',
    'filament-panel-base::components.locale-switcher',
    'filament-panel-base::components.panel-badge',
    'filament-panel-base::components.phone-input',
    'filament-panel-base::components.powered-by',
    'filament-panel-base::components.session-expiry-handler',
    'filament-panel-base::components.social-provider-icon',
    'filament-panel-base::components.visit-website-button',
]);
