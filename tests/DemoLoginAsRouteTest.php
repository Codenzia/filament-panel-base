<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\FilamentPanelBaseServiceProvider;
use Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage;
use Illuminate\Database\Eloquent\Model;
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
 * A demo page that narrows which accounts the button offers. The endpoint must
 * apply the same narrowing — otherwise the override is decoration.
 */
class RestrictedDemoPage extends DemoPage
{
    protected function canLogInAs(Model $user): bool
    {
        return parent::canLogInAs($user) && $user->getAttribute('name') !== 'Denied';
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
        $table->boolean('is_protected')->default(false);
    });
});

afterEach(function (): void {
    Schema::dropIfExists('users');
});

it('logs in the target user and redirects to the app', function () {
    $user = DemoRouteUser::create(['name' => 'Editor', 'role' => 'editor']);

    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->post(route('filament-panel-base.demo.login-as', $user->getKey()))
        ->assertRedirect(url('/admin'));

    expect(Auth::id())->toBe($user->getKey());
});

it('forbids switching into the admin role', function () {
    $admin = DemoRouteUser::create(['name' => 'Root', 'role' => 'super_admin']);

    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->post(route('filament-panel-base.demo.login-as', $admin->getKey()))
        ->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

it('forbids the switch when the demo is locked', function () {
    $user = DemoRouteUser::create(['name' => 'Editor', 'role' => 'editor']);

    $this->post(route('filament-panel-base.demo.login-as', $user->getKey()))
        ->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

it('404s for an unknown user id', function () {
    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->post(route('filament-panel-base.demo.login-as', 999999))
        ->assertNotFound();
});

it('refuses a GET so a cross-site link cannot change who you are', function () {
    $user = DemoRouteUser::create(['name' => 'Editor', 'role' => 'editor']);

    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->get(route('filament-panel-base.demo.login-as', $user->getKey()))
        ->assertMethodNotAllowed();

    expect(Auth::check())->toBeFalse();
});

it('forbids switching into an account the host marked protected', function () {
    $user = DemoRouteUser::create(['name' => 'Owner', 'role' => 'editor', 'is_protected' => true]);

    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->post(route('filament-panel-base.demo.login-as', $user->getKey()))
        ->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

it('honours the demo page override on direct requests', function () {
    config()->set('filament-panel-base.demo.component', RestrictedDemoPage::class);

    $provider = $this->app->getProvider(FilamentPanelBaseServiceProvider::class);
    $boot = new ReflectionMethod($provider, 'bootDemoModule');
    $boot->setAccessible(true);
    $boot->invoke($provider);

    $denied = DemoRouteUser::create(['name' => 'Denied', 'role' => 'editor']);
    $allowed = DemoRouteUser::create(['name' => 'Editor', 'role' => 'editor']);

    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->post(route('filament-panel-base.demo.login-as', $denied->getKey()))
        ->assertForbidden();

    expect(Auth::check())->toBeFalse();

    $this->withSession(['filament-panel-base.demo.unlocked' => true])
        ->post(route('filament-panel-base.demo.login-as', $allowed->getKey()))
        ->assertRedirect(url('/admin'));

    expect(Auth::id())->toBe($allowed->getKey());
});
