<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    config()->set('app.name', 'Testbrand');
    config()->set('filament-panel-base.colors.primary', '#0787F8');
    config()->set('filament-panel-base.errors.logo', null);
    config()->set('filament-panel-base.errors.tagline', null);
    config()->set('filament-panel-base.support_email', null);
});

it('resolves the package branded page for a bare errors.404 when the app ships none', function () {
    $html = view('errors.404')->render();

    expect($html)
        ->toContain('404')
        ->toContain('Page not found')
        ->toContain('Testbrand')                 // app name wordmark (no logo configured)
        ->toContain('الصفحة التي تبحث عنها')      // baked Arabic line
        ->not->toContain('Reference')            // ref/report are 500-only
        ->not->toContain('Whoops')               // no Laravel stack-trace chrome
        ->not->toContain('Stack trace');
});

it('renders the 500 page with a reference id and no support button when support_email is unset', function () {
    $html = view('errors.500')->render();

    expect($html)
        ->toContain('500')
        ->toContain('Something went wrong')
        ->toContain('Reference')
        ->toMatch('/<code id="pnb-ref">[A-Z0-9-]+<\/code>/')
        ->not->toContain('mailto:');             // hidden without support_email
});

it('shows the mailto report button on 500 only when support_email is configured', function () {
    config()->set('filament-panel-base.support_email', 'help@example.test');

    $five = view('errors.500')->render();
    expect($five)
        ->toContain('mailto:help@example.test')
        ->toContain('Report this issue');

    // Non-server codes never get the reference chip or the report button.
    $four = view('errors.404')->render();
    expect($four)
        ->not->toContain('Reference')
        ->not->toContain('mailto:');
});

it('renders an <img> wordmark when a logo asset is configured', function () {
    config()->set('filament-panel-base.errors.logo', 'brand/logo-light.png');

    $html = view('errors.404')->render();

    expect($html)
        ->toContain('brand/logo-light.png')
        ->toContain('<img');
});

it('lets the host app override a single code with its own errors view', function () {
    $tmp = sys_get_temp_dir().'/pnb-error-override-'.uniqid();
    File::ensureDirectoryExists($tmp.'/errors');
    File::put($tmp.'/errors/404.blade.php', 'APP OWN 404 PAGE');

    // Mimic the host app's resources/views sitting ahead of the package
    // location (the framework registers app paths first; the package
    // *appends* its own, so app views win).
    View::getFinder()->prependLocation($tmp);
    View::getFinder()->flush();

    $html = view('errors.404')->render();

    expect($html)
        ->toContain('APP OWN 404 PAGE')
        ->not->toContain('Page not found');

    File::deleteDirectory($tmp);
});

it('keeps every error view database-independent (no __() / Eloquent / auth)', function () {
    $dirs = [
        __DIR__.'/../resources/error-pages/errors',
        __DIR__.'/../resources/views/errors',
    ];

    $forbidden = ['__(', '@lang', 'DB::', '::query(', 'Auth::', 'auth()', 'Eloquent'];

    foreach ($dirs as $dir) {
        foreach (glob($dir.'/*.blade.php') as $file) {
            $source = file_get_contents($file);
            foreach ($forbidden as $needle) {
                expect($source)->not->toContain($needle, "{$file} must not contain {$needle}");
            }
        }
    }
});
