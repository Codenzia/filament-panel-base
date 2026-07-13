<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Filament\Pages\AnalyticsPage;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

/**
 * AnalyticsPage::canAccess() is the page's authorisation boundary. It must
 * fail closed for guests and non-admins, allow the configured admin role,
 * respect a custom admin_role, and treat a role-system error as "no".
 */
it('denies access to guests', function (): void {
    expect(AnalyticsPage::canAccess())->toBeFalse();
});

it('allows a user holding the configured admin role', function (): void {
    config()->set('filament-panel-base.admin_role', 'super_admin');

    Auth::setUser(new class extends User
    {
        public function hasRole(string $role): bool
        {
            return $role === 'super_admin';
        }
    });

    expect(AnalyticsPage::canAccess())->toBeTrue();
});

it('denies a user who lacks the admin role', function (): void {
    config()->set('filament-panel-base.admin_role', 'super_admin');

    Auth::setUser(new class extends User
    {
        public function hasRole(string $role): bool
        {
            return $role === 'editor';
        }
    });

    expect(AnalyticsPage::canAccess())->toBeFalse();
});

it('respects a custom admin_role config', function (): void {
    config()->set('filament-panel-base.admin_role', 'analyst');

    Auth::setUser(new class extends User
    {
        public function hasRole(string $role): bool
        {
            return $role === 'analyst';
        }
    });

    expect(AnalyticsPage::canAccess())->toBeTrue();
});

it('allows any authenticated user when the model has no role system', function (): void {
    Auth::setUser(new class extends User {});

    expect(AnalyticsPage::canAccess())->toBeTrue();
});

it('fails closed when hasRole() throws (e.g. roles table missing)', function (): void {
    Auth::setUser(new class extends User
    {
        public function hasRole(string $role): bool
        {
            throw new RuntimeException('roles table missing');
        }
    });

    expect(AnalyticsPage::canAccess())->toBeFalse();
});
