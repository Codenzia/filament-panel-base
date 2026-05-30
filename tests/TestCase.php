<?php

namespace Codenzia\FilamentPanelBase\Tests;

use Codenzia\FilamentPanelBase\FilamentPanelBaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelSettingsServiceProvider::class,
            FilamentPanelBaseServiceProvider::class,
            SocialiteServiceProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Socialite' => Socialite::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Build an AuthenticationSettings (or any Spatie Settings) instance for
     * unit tests, bypassing the package's hydration constructor.
     *
     * @template T of object
     *
     * @param  class-string<T>  $settingsClass
     * @return T
     */
    protected function settingsStub(string $settingsClass): object
    {
        $reflection = new \ReflectionClass($settingsClass);

        return $reflection->newInstanceWithoutConstructor();
    }

    /**
     * Create a `users` table matching what the host app's User migration
     * would have created, plus the 2FA columns added by the package's
     * auto-loaded migration. Used by tests that need to persist a User.
     */
    protected function createUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Create the `sessions` table Laravel's session driver expects when
     * SESSION_DRIVER=database. Used by session-management tests.
     */
    protected function createSessionsTable(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Create the Spatie `settings` table so settings-backed code paths
     * can read/write without crashing.
     */
    protected function createSettingsTable(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group')->index();
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['group', 'name']);
        });
    }
}
