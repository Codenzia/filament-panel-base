<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Illuminate\Translation\FileLoader;
use Spatie\TranslationLoader\TranslationLoaderManager;

/**
 * spatie/laravel-translation-loader rebinds `translation.loader` with the
 * application lang path as its only path, dropping the framework's own lang
 * directory that Laravel registers first. Every consumer of this package
 * gets spatie transitively, so the package puts the path back.
 */

/**
 * Rebind `translation.loader` exactly the way spatie's provider does, then
 * drop the resolved translator so the next lookup goes through it.
 */
function bindSpatieTranslationLoader(): void
{
    app()->singleton('translation.loader', function ($app) {
        return new TranslationLoaderManager($app['files'], $app['path.lang']);
    });

    app()->forgetInstance('translator');
    Facade::clearResolvedInstance('translator');
}

afterEach(function (): void {
    File::deleteDirectory(lang_path('en'));
});

it('keeps framework translations resolvable under spatie\'s loader', function (): void {
    bindSpatieTranslationLoader();

    expect(app('translation.loader'))->toBeInstanceOf(TranslationLoaderManager::class)
        ->and(__('validation.required', ['attribute' => 'email']))->toBe('The email field is required.')
        ->and(__('pagination.previous'))->toBe('&laquo; Previous');
});

it('still lets an application lang file override the framework text', function (): void {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(
        lang_path('en/validation.php'),
        '<?php return ["required" => "We need :attribute."];'
    );

    bindSpatieTranslationLoader();

    expect(__('validation.required', ['attribute' => 'email']))->toBe('We need email.');
});

it('leaves a loader that already has the framework path untouched', function (): void {
    $loader = app('translation.loader');

    expect($loader)->toBeInstanceOf(FileLoader::class)
        ->and(app('translation.loader'))->toBe($loader);
});

it('does not touch a loader that is not a file loader', function (): void {
    $loader = new class implements Loader
    {
        public function load($locale, $group, $namespace = null): array
        {
            return [];
        }

        public function addNamespace($namespace, $hint): void {}

        public function addJsonPath($path): void {}

        public function namespaces(): array
        {
            return [];
        }
    };

    app()->singleton('translation.loader', fn () => $loader);
    app()->forgetInstance('translator');
    Facade::clearResolvedInstance('translator');

    expect(app('translation.loader'))->toBe($loader);
});
