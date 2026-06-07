<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Filament\Resources\UserResource;
use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;

afterEach(function (): void {
    UserResource::$authorizeUsing = null;
    UserResource::$extraSchemaUsing = null;
    UserResource::$columnsUsing = null;
    UserResource::$filtersUsing = null;
    UserResource::$recordActionsUsing = null;
});

it('is opt-in: the Users resource stays off until withUserManagement() is called', function (): void {
    expect(FilamentPanelBasePlugin::make()->isUserManagementEnabled())->toBeFalse();
    expect(FilamentPanelBasePlugin::make()->withUserManagement()->isUserManagementEnabled())->toBeTrue();
});

it('gates access through the host-supplied authorize closure', function (): void {
    FilamentPanelBasePlugin::make()->withUserManagement(authorize: fn (): bool => false);
    expect(UserResource::canAccess())->toBeFalse();

    FilamentPanelBasePlugin::make()->withUserManagement(authorize: fn (): bool => true);
    expect(UserResource::canAccess())->toBeTrue();
});

it('applies scalar navigation overrides (e.g. a custom group)', function (): void {
    FilamentPanelBasePlugin::make()->withUserManagement(navigationGroup: 'Users & roles', navigationSort: 7);

    expect(UserResource::getNavigationGroup())->toBe('Users & roles');
    expect(UserResource::getNavigationSort())->toBe(7);
});

it('falls back to the app auth user model when none is configured', function (): void {
    config([
        'filament-panel-base.user_management.model' => null,
        'auth.providers.users.model' => 'App\\Models\\Widget',
    ]);

    expect(UserResource::getModel())->toBe('App\\Models\\Widget');
});

it('keeps closures off config so config:cache stays serializable', function (): void {
    FilamentPanelBasePlugin::make()->withUserManagement(authorize: fn (): bool => true);

    // The closure lives on the resource (a static), never in the config array.
    expect(config('filament-panel-base.user_management'))->not->toHaveKey('authorize');
    expect(UserResource::$authorizeUsing)->toBeInstanceOf(Closure::class);
});

it('lets a host reshape the table (prepend/append/reorder columns)', function (): void {
    // A richer per-app table (e.g. task-off: Photo first, Department appended) without forking.
    FilamentPanelBasePlugin::make()->withUserManagement(
        tableColumns: fn (array $default): array => ['photo', ...$default, 'department'],
        tableFilters: fn (array $default): array => [...$default, 'department_filter'],
    );

    expect(UserResource::$columnsUsing)->toBeInstanceOf(Closure::class);

    $columns = (UserResource::$columnsUsing)(['name', 'email']);
    expect($columns)->toBe(['photo', 'name', 'email', 'department']);

    $filters = (UserResource::$filtersUsing)(['roles']);
    expect($filters)->toBe(['roles', 'department_filter']);
});
