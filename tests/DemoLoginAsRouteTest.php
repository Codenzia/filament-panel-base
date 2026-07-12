<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\FilamentPanelBaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DemoRouteUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}

/**
 * The demo module (and its login-as route) only register when
 * filament-panel-base.demo.enabled is true at boot. Enable it + point the user
 * model at the test model, then re-invoke the provider's demo boot so its real
 * route registration runs (its booted() callback fires immediately because the
 * app is already booted).
 */
beforeEach(function (): void {
    config()->set('filament-panel-base.demo.enabled', true);
    config()->set('filament-panel-base.demo.route', '/demo');
    config()->set('filament-panel-base.demo.app_url', '/admin');
    config()->set('filament-panel-base.demo.middleware', ['web']);
    config()->set('filament-panel-base.user_model', DemoRouteUser::class);
    config()->set('auth.providers.users.model', DemoRouteUser::class);

    $provider = $this->app->getProvider(FilamentPanelBaseServiceProvider::class);
    $boot = new ReflectionMethod($provider, 'bootDemoModule');
    $boot->setAccessible(true);
    $boot->invoke($provider);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('role')->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('users');
});

it('logs in the target user and redirects to the app', function () {
    $user = DemoRouteUser::create(['name' => 'Editor', 'role' => 'editor']);

    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->get(route('filament-panel-base.demo.login-as', $user->getKey()))
        ->assertRedirect(url('/admin'));

    expect(Auth::id())->toBe($user->getKey());
});

it('forbids switching into the admin role', function () {
    $admin = DemoRouteUser::create(['name' => 'Root', 'role' => 'super_admin']);

    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->get(route('filament-panel-base.demo.login-as', $admin->getKey()))
        ->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

it('forbids the switch when the demo is locked', function () {
    $user = DemoRouteUser::create(['name' => 'Editor', 'role' => 'editor']);

    $this->get(route('filament-panel-base.demo.login-as', $user->getKey()))
        ->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

it('404s for an unknown user id', function () {
    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->get(route('filament-panel-base.demo.login-as', 999999))
        ->assertNotFound();
});
