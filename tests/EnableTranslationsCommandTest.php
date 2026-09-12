<?php

use Codenzia\FilamentPanelBase\Commands\EnableTranslationsCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Spatie\TranslationLoader\TranslationServiceProvider;

beforeEach(function () {
    // spatie/laravel-translation-loader ships a DeferrableProvider, so Testbench
    // never boots it on its own and its publish groups stay unregistered.
    // The command only works against a booted provider, so boot it here too.
    $this->app->register(TranslationServiceProvider::class);
});

afterEach(function () {
    foreach (File::glob(database_path('migrations/*_create_language_lines_table.php')) as $published) {
        File::delete($published);
    }

    if (File::exists(config_path('translation-loader.php'))) {
        File::delete(config_path('translation-loader.php'));
    }
});

/**
 * The command used to publish under the bare 'config' / 'migrations' tags, which
 * spatie/laravel-translation-loader does not expose — laravel-package-tools
 * prefixes every tag with the package short name. vendor:publish matched nothing,
 * exited 0, and left the consumer with ->withTranslations() enabled and no
 * language_lines table, so every __() threw a QueryException and probed
 * Schema::hasTable() once per translation group, per request.
 */
it('publishes under tags the provider actually registers', function () {
    $tags = (new ReflectionClass(EnableTranslationsCommand::class))
        ->getConstant('PUBLISH_TAGS');

    expect($tags)->not->toBeEmpty();

    foreach ($tags as $tag) {
        expect(array_keys(ServiceProvider::$publishGroups))->toContain($tag);
    }
});

it('actually writes the config and migration to the app', function () {
    $this->artisan('filament-panel-base:enable-translations')->assertSuccessful();

    expect(File::exists(config_path('translation-loader.php')))->toBeTrue()
        ->and(File::glob(database_path('migrations/*_create_language_lines_table.php')))->not->toBeEmpty();
});

it('tells the operator that the published stub needs a deleted_at column', function () {
    // Codenzia\FilamentPanelBase\Models\Translation is a SoftDeletes LanguageLine,
    // but spatie's stub creates no deleted_at, so the panel's Translations screen
    // breaks on "no such column" unless the operator adds it before migrating.
    $this->artisan('filament-panel-base:enable-translations')
        ->expectsOutputToContain('deleted_at')
        ->assertSuccessful();
});
