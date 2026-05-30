<?php

use Codenzia\FilamentPanelBase\Analytics\Services\UserAgentParser;
use Codenzia\FilamentPanelBase\Sessions\Events\NewDeviceLogin;
use Codenzia\FilamentPanelBase\Sessions\Listeners\DetectNewDeviceLogin;
use Codenzia\FilamentPanelBase\Sessions\Services\DeviceSessionRepository;
use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->createUsersTable();
    $this->createSessionsTable();

    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    $settings = $this->settingsStub(SessionManagementSettings::class);
    $settings->enabled = true;
    $settings->notify_on_new_device = true;
    app()->instance(SessionManagementSettings::class, $settings);

    $this->user = TwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);

    $this->listener = new DetectNewDeviceLogin(
        $settings,
        new DeviceSessionRepository(new UserAgentParser),
    );

    // Pin the request so the listener has IP + UA to fingerprint.
    // Pass server vars at creation time — Request::create() caches IP on
    // construction so setting them later via $request->server->set() is
    // silently ignored by getClientIp().
    $request = Request::create('/login', 'POST', [], [], [], [
        'REMOTE_ADDR' => '203.0.113.50',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome',
    ]);
    app()->instance('request', $request);
});

it('fires NewDeviceLogin on a first-ever sign-in from this IP+UA', function (): void {
    Event::fake([NewDeviceLogin::class]);

    $this->listener->handle(new Login('web', $this->user, false));

    Event::assertDispatched(NewDeviceLogin::class);
});

it('does not fire when the IP+UA pair was seen before', function (): void {
    DB::table('sessions')->insert([
        'id' => 'previous-session',
        'user_id' => $this->user->id,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Mozilla/5.0 Chrome',
        'payload' => '',
        'last_activity' => now()->subHour()->timestamp,
    ]);

    Event::fake([NewDeviceLogin::class]);

    $this->listener->handle(new Login('web', $this->user, false));

    Event::assertNotDispatched(NewDeviceLogin::class);
});

it('fires when only the IP matches but UA differs', function (): void {
    DB::table('sessions')->insert([
        'id' => 'previous-session',
        'user_id' => $this->user->id,
        'ip_address' => '203.0.113.50',
        'user_agent' => 'Some other browser',
        'payload' => '',
        'last_activity' => now()->subHour()->timestamp,
    ]);

    Event::fake([NewDeviceLogin::class]);

    $this->listener->handle(new Login('web', $this->user, false));

    Event::assertDispatched(NewDeviceLogin::class);
});

it('short-circuits when the module is disabled', function (): void {
    $settings = $this->settingsStub(SessionManagementSettings::class);
    $settings->enabled = false;
    $settings->notify_on_new_device = true;
    app()->instance(SessionManagementSettings::class, $settings);

    $listener = new DetectNewDeviceLogin(
        $settings,
        new DeviceSessionRepository(new UserAgentParser),
    );

    Event::fake([NewDeviceLogin::class]);

    $listener->handle(new Login('web', $this->user, false));

    Event::assertNotDispatched(NewDeviceLogin::class);
});

it('short-circuits when notify_on_new_device is off', function (): void {
    $settings = $this->settingsStub(SessionManagementSettings::class);
    $settings->enabled = true;
    $settings->notify_on_new_device = false;
    app()->instance(SessionManagementSettings::class, $settings);

    $listener = new DetectNewDeviceLogin(
        $settings,
        new DeviceSessionRepository(new UserAgentParser),
    );

    Event::fake([NewDeviceLogin::class]);

    $listener->handle(new Login('web', $this->user, false));

    Event::assertNotDispatched(NewDeviceLogin::class);
});

it('does not throw when the sessions table is missing', function (): void {
    \Illuminate\Support\Facades\Schema::dropIfExists('sessions');

    Event::fake([NewDeviceLogin::class]);

    $this->listener->handle(new Login('web', $this->user, false));

    Event::assertNotDispatched(NewDeviceLogin::class);
});
