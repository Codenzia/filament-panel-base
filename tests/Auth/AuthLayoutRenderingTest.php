<?php

use Codenzia\FilamentPanelBase\Auth\Livewire\ForgotPassword;
use Codenzia\FilamentPanelBase\Auth\Livewire\Login;
use Codenzia\FilamentPanelBase\Auth\Livewire\Register;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/*
 * Two ways a host's `auth.layout` used to lose the sign-in form:
 *
 *   - naming a view the application does not ship threw MissingLayoutException,
 *     a 500 on the only route into the account area (serveeta);
 *   - naming a section-based layout rendered the host's page shell around an
 *     empty content section, so the page came back 200 with no form on it and
 *     nothing in the log (snapcar, toolenza).
 */

beforeEach(function () {
    $this->createUsersTable();
    config()->set('session.driver', 'array');

    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->credentials_mode = 'email';
    $settings->registration_mode = 'open';
    $settings->phone_required = false;
    $settings->social_providers_enabled = [];
    $settings->throttle_per_minute = 5;
    $settings->throttle_per_day = 50;
    app()->instance(AuthenticationSettings::class, $settings);

    $this->layouts = sys_get_temp_dir().'/fpb-auth-layouts-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->layouts);
    view()->addLocation($this->layouts);
});

afterEach(function () {
    File::deleteDirectory($this->layouts);
});

function writeLayout(string $dir, string $name, string $body): void
{
    File::put($dir.'/'.$name.'.blade.php', $body);
}

it('renders the page into a component-style layout', function () {
    writeLayout($this->layouts, 'host-slot', '<div id="chrome">{{ $slot }}</div>');
    config(['filament-panel-base.auth.layout' => 'host-slot']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('id="chrome"', false)
        ->assertSee('wire:submit="login"', false);
});

it('renders the page into a section-based layout when the section is named', function () {
    writeLayout($this->layouts, 'host-section', '<div id="chrome">@yield("content")</div>');
    config([
        'filament-panel-base.auth.layout' => 'host-section',
        'filament-panel-base.auth.layout_section' => 'content',
    ]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('id="chrome"', false)
        ->assertSee('wire:submit="login"', false);
});

it('falls back to the bundled layout, with a warning, when the configured one is missing', function () {
    Log::shouldReceive('warning')
        ->atLeast()->once()
        ->withArgs(fn (string $message, array $context = []) => ($context['configured'] ?? null) === 'layouts.nothing-here');

    config(['filament-panel-base.auth.layout' => 'layouts.nothing-here']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('fpb-auth-card', false)
        ->assertSee('wire:submit="login"', false);
});

it('keeps the form on the page for every auth screen behind a section-based layout', function (string $path, string $marker) {
    writeLayout($this->layouts, 'host-section', '<div id="chrome">@yield("content")</div>');
    config([
        'filament-panel-base.auth.layout' => 'host-section',
        'filament-panel-base.auth.layout_section' => 'content',
    ]);

    $this->get($path)
        ->assertOk()
        ->assertSee('id="chrome"', false)
        ->assertSee($marker, false);
})->with([
    ['/login', 'wire:submit="login"'],
    ['/register', 'wire:submit="register"'],
    ['/forgot-password', 'wire:submit='],
]);

it('still ships the components that render these pages', function () {
    expect(class_exists(Login::class))->toBeTrue()
        ->and(class_exists(Register::class))->toBeTrue()
        ->and(class_exists(ForgotPassword::class))->toBeTrue();
});
