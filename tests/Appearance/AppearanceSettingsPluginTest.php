<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Filament\Pages\ManageAppearanceSettings;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Illuminate\Foundation\Auth\User as Authenticatable;

afterEach(function (): void {
    ManageAppearanceSettings::$authorizeUsing = null;
});

/** User exposing a spatie-style hasRole() but no isSuperAdmin(). */
class _AppearanceRoleUser extends Authenticatable
{
    protected $guarded = [];

    /** @var array<int, string> */
    public array $roles = [];

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}

/** User with no role system at all (no hasRole, no isSuperAdmin). */
class _AppearancePlainUser extends Authenticatable
{
    protected $guarded = [];
}

it('is opt-in: the Appearance page stays off until withAppearanceSettings() is called', function (): void {
    expect(FilamentPanelBasePlugin::make()->hasAppearanceSettingsPage())->toBeFalse();
    expect(FilamentPanelBasePlugin::make()->withAppearanceSettings()->hasAppearanceSettingsPage())->toBeTrue();
});

it('gates access through the host-supplied authorize closure', function (): void {
    FilamentPanelBasePlugin::make()->withAppearanceSettings(authorize: fn (): bool => false);
    expect(ManageAppearanceSettings::canAccess())->toBeFalse();

    FilamentPanelBasePlugin::make()->withAppearanceSettings(authorize: fn (): bool => true);
    expect(ManageAppearanceSettings::canAccess())->toBeTrue();
});

it('denies access to an unauthenticated visitor (PNB-035)', function (): void {
    expect(ManageAppearanceSettings::canAccess())->toBeFalse();
});

it('fails closed for a user lacking the configured admin role (PNB-035)', function (): void {
    // Previously canAccess() fell OPEN (returned true) for any authenticated
    // user whenever the model had no isSuperAdmin(). It must now gate on the
    // configured admin role and deny users who lack it.
    config()->set('filament-panel-base.admin_role', 'super_admin');

    $user = new _AppearanceRoleUser;
    $user->roles = ['editor'];
    $this->actingAs($user);

    expect(ManageAppearanceSettings::canAccess())->toBeFalse();
});

it('grants access to a user holding the configured admin role (PNB-035)', function (): void {
    config()->set('filament-panel-base.admin_role', 'super_admin');

    $user = new _AppearanceRoleUser;
    $user->roles = ['super_admin'];
    $this->actingAs($user);

    expect(ManageAppearanceSettings::canAccess())->toBeTrue();
});

it('remains open only when the host has no role system at all (PNB-035)', function (): void {
    // No isSuperAdmin(), no hasRole() — the documented "any authenticated user"
    // fallback. This is the ONLY branch that returns true without a role check.
    $this->actingAs(new _AppearancePlainUser);

    expect(ManageAppearanceSettings::canAccess())->toBeTrue();
});

it('applies navigation overrides via config', function (): void {
    FilamentPanelBasePlugin::make()->withAppearanceSettings(navigationGroup: 'Branding', navigationSort: 5);

    expect(ManageAppearanceSettings::getNavigationGroup())->toBe('Branding');
    expect(ManageAppearanceSettings::getNavigationSort())->toBe(5);
});
