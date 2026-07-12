<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage;
use Codenzia\FilamentPanelBase\Models\DemoSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Schema;

/**
 * Set the env variable everywhere Laravel's env() helper might read it.
 * Vinkla/Dotenv's repository sits on top of getenv(), $_ENV, and $_SERVER;
 * setting via putenv() alone leaves $_ENV stale, which is why some tests
 * see the wrong value if you only use putenv().
 */
function setDemoEnv(?string $key, ?string $value): void
{
    $key ??= 'APP_DEMO_PAGE_PWD';
    if ($value === null) {
        Env::getRepository()->clear($key);
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    } else {
        Env::getRepository()->set($key, $value);
    }

    // The demo password is now resolved from config (env() is forbidden in app
    // logic and would be null under config:cache). Mirror the value into the
    // config key the code reads so the DB-first / fallback tests still exercise
    // the real resolution path.
    config(['filament-panel-base.demo.password' => $value]);
}

beforeEach(function () {
    Schema::create('demo_settings', function ($table) {
        $table->id();
        $table->text('password')->nullable();
        $table->timestamp('rotated_at')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamps();
    });
    setDemoEnv('APP_DEMO_PAGE_PWD', null);
});

afterEach(function () {
    Schema::dropIfExists('demo_settings');
    setDemoEnv('APP_DEMO_PAGE_PWD', null);
});

// A test-double that exposes the protected hooks we need to assert against.
class DemoPageTestDouble extends DemoPage
{
    public function callExpectedPassword(): ?string
    {
        return $this->expectedPassword();
    }

    public function callTouchLastUsedAt(): void
    {
        $this->touchLastUsedAt();
    }

    public function callCanLogInAs(Model $user): bool
    {
        return $this->canLogInAs($user);
    }

    public function callIsSuperAdmin(Model $user): bool
    {
        return $this->isSuperAdmin($user);
    }
}

// ---------------------------------------------------------------------------
// expectedPassword(): DB-first / .env fallback
// ---------------------------------------------------------------------------

it('returns the DB row password when set (DB beats env)', function () {
    $row = DemoSetting::current();
    $row->password = 'from-the-database';
    $row->save();

    setDemoEnv('APP_DEMO_PAGE_PWD', 'from-env');

    $page = new DemoPageTestDouble;
    expect($page->callExpectedPassword())->toBe('from-the-database');
});

it('falls back to env when the DB row password is null', function () {
    DemoSetting::current(); // create the row but leave password null
    setDemoEnv('APP_DEMO_PAGE_PWD', 'env-fallback-value');

    $page = new DemoPageTestDouble;
    expect($page->callExpectedPassword())->toBe('env-fallback-value');
});

it('falls back to env when the DB row password is the empty string', function () {
    $row = DemoSetting::current();
    $row->password = '';
    $row->save();

    setDemoEnv('APP_DEMO_PAGE_PWD', 'env-after-empty');

    $page = new DemoPageTestDouble;
    expect($page->callExpectedPassword())->toBe('env-after-empty');
});

it('falls back to env when the demo_settings table does not exist', function () {
    Schema::dropIfExists('demo_settings');
    setDemoEnv('APP_DEMO_PAGE_PWD', 'env-no-table');

    $page = new DemoPageTestDouble;
    expect($page->callExpectedPassword())->toBe('env-no-table');
});

it('returns null when neither DB nor env has a value (gate disabled)', function () {
    DemoSetting::current(); // row exists, password null
    // No env var set in beforeEach.

    $page = new DemoPageTestDouble;
    expect($page->callExpectedPassword())->toBeNull();
});

it('reads the demo password from config when the DB row is empty', function () {
    config(['filament-panel-base.demo.password' => 'config-value']);

    $page = new DemoPageTestDouble;
    expect($page->callExpectedPassword())->toBe('config-value');
});

// ---------------------------------------------------------------------------
// touchLastUsedAt(): updates the singleton without breaking the gate
// ---------------------------------------------------------------------------

it('touchLastUsedAt() bumps the timestamp on the singleton row', function () {
    $row = DemoSetting::current();
    expect($row->last_used_at)->toBeNull();

    $page = new DemoPageTestDouble;
    $before = now();
    $page->callTouchLastUsedAt();
    $after = now();

    $reloaded = DemoSetting::current();
    expect($reloaded->last_used_at)->not->toBeNull()
        ->and($reloaded->last_used_at->between($before->copy()->subSecond(), $after->copy()->addSecond()))
        ->toBeTrue();
});

it('touchLastUsedAt() no-ops when the table is missing (gate stays functional)', function () {
    Schema::dropIfExists('demo_settings');

    $page = new DemoPageTestDouble;
    // Must not throw — defensive try/catch in the implementation.
    $page->callTouchLastUsedAt();
    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// mount(): allow_empty_password flag controls the empty-password fallback
// ---------------------------------------------------------------------------

it('mount() keeps the gate locked when no password is configured (secure default)', function () {
    // No env var, no DB row password — expectedPassword() returns null.
    config(['filament-panel-base.demo.allow_empty_password' => false]);
    session()->forget('filament-panel-base.demo.unlocked');

    $page = new DemoPageTestDouble;
    $page->mount();

    expect(session('filament-panel-base.demo.unlocked'))->not->toBeTrue();
});

it('mount() auto-unlocks when password is empty AND allow_empty_password is true', function () {
    config(['filament-panel-base.demo.allow_empty_password' => true]);
    session()->forget('filament-panel-base.demo.unlocked');

    $page = new DemoPageTestDouble;
    $page->mount();

    expect(session('filament-panel-base.demo.unlocked'))->toBeTrue();
});

it('mount() leaves a previously-unlocked session unlocked regardless of flag', function () {
    config(['filament-panel-base.demo.allow_empty_password' => false]);
    session(['filament-panel-base.demo.unlocked' => true]);

    $page = new DemoPageTestDouble;
    $page->mount();

    expect(session('filament-panel-base.demo.unlocked'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// canLogInAs(): default blocks super_admin
// ---------------------------------------------------------------------------

it('canLogInAs() blocks users with the super_admin role by default', function () {
    $user = new class extends Model
    {
        public function hasRole(string $role): bool
        {
            return $role === 'super_admin';
        }
    };

    $page = new DemoPageTestDouble;
    expect($page->callIsSuperAdmin($user))->toBeTrue()
        ->and($page->callCanLogInAs($user))->toBeFalse();
});

it('canLogInAs() allows users without the super_admin role', function () {
    $user = new class extends Model
    {
        public function hasRole(string $role): bool
        {
            return $role === 'editor';
        }
    };

    $page = new DemoPageTestDouble;
    expect($page->callIsSuperAdmin($user))->toBeFalse()
        ->and($page->callCanLogInAs($user))->toBeTrue();
});

it('canLogInAs() allows users on models without hasRole() (no Spatie permission)', function () {
    $user = new class extends Model {};

    $page = new DemoPageTestDouble;
    expect($page->callIsSuperAdmin($user))->toBeFalse()
        ->and($page->callCanLogInAs($user))->toBeTrue();
});

it('canLogInAs() respects a custom admin_role config', function () {
    config(['filament-panel-base.admin_role' => 'root']);

    $user = new class extends Model
    {
        public function hasRole(string $role): bool
        {
            return $role === 'root';
        }
    };

    $page = new DemoPageTestDouble;
    expect($page->callIsSuperAdmin($user))->toBeTrue()
        ->and($page->callCanLogInAs($user))->toBeFalse();
});

it('canLogInAs() treats a hasRole() exception as not-super-admin', function () {
    $user = new class extends Model
    {
        public function hasRole(string $role): bool
        {
            throw new RuntimeException('roles table missing');
        }
    };

    $page = new DemoPageTestDouble;
    expect($page->callIsSuperAdmin($user))->toBeFalse()
        ->and($page->callCanLogInAs($user))->toBeTrue();
});

// ---------------------------------------------------------------------------
// loginAs(): gate flag + super-admin authorization boundary
// ---------------------------------------------------------------------------

it('loginAs() refuses to authenticate when the gate session flag is absent', function () {
    session()->forget('filament-panel-base.demo.unlocked');

    $page = new DemoPageTestDouble;
    $page->loginAs(1);

    expect(auth()->check())->toBeFalse();
});

it('loginAs() refuses a super_admin target even when the gate is unlocked', function () {
    Schema::create('demo_login_users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('role')->nullable();
        $table->timestamps();
    });

    config(['filament-panel-base.user_model' => DemoLoginTestUser::class]);
    config(['filament-panel-base.admin_role' => 'super_admin']);

    $admin = DemoLoginTestUser::create(['name' => 'Root', 'role' => 'super_admin']);

    session(['filament-panel-base.demo.unlocked' => true]);

    $page = new DemoPageTestDouble;
    $page->loginAs($admin->getKey());

    expect(auth()->check())->toBeFalse();

    Schema::dropIfExists('demo_login_users');
});

class DemoLoginTestUser extends User
{
    protected $table = 'demo_login_users';

    protected $guarded = [];

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}
