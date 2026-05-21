<?php

namespace Codenzia\FilamentPanelBase\Tests;

use Codenzia\FilamentPanelBase\FilamentPanelBaseServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
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
}
