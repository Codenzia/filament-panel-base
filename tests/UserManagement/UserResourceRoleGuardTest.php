<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Filament\Resources\UserResource;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * PNB-021: the super-admin role must never be assignable by an actor who is not
 * themselves a super-admin. The Roles select filters its options through
 * UserResource::actorCanAssignSuperAdmin(); this guards that decision.
 */
it('uses the configured super-admin role name', function (): void {
    expect(UserResource::superAdminRoleName())->toBe('super_admin');

    config()->set('filament-shield.super_admin.name', 'root');

    expect(UserResource::superAdminRoleName())->toBe('root');
});

it('forbids a guest or a non-super-admin from assigning the super-admin role (PNB-021)', function (): void {
    expect(UserResource::actorCanAssignSuperAdmin())->toBeFalse();

    $actor = new RoleGuardTestUser;
    $actor->isSuper = false;
    $this->actingAs($actor);

    expect(UserResource::actorCanAssignSuperAdmin())->toBeFalse();
});

it('allows a super-admin to assign the super-admin role (PNB-021)', function (): void {
    $actor = new RoleGuardTestUser;
    $actor->isSuper = true;
    $this->actingAs($actor);

    expect(UserResource::actorCanAssignSuperAdmin())->toBeTrue();
});

class RoleGuardTestUser extends AuthUser
{
    public bool $isSuper = false;

    protected $guarded = [];

    public function hasRole(string $role): bool
    {
        return $this->isSuper && $role === UserResource::superAdminRoleName();
    }
}
