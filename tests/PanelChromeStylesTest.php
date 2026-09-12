<?php

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\Facades\Blade;

/**
 * The components this package injects into consumer panels were laid out with
 * Tailwind utility classes, which live in an app's *own* compiled theme. A
 * panel running the stock Filament build has none of them, so the markup
 * collapsed. The layout therefore ships as a packaged stylesheet, scoped under
 * the `fpb-` prefix, registered with Filament's asset system.
 */
if (! function_exists('fpbStylesheet')) {
    function fpbStylesheet(): string
    {
        return file_get_contents(__DIR__.'/../resources/dist/panel-base.css');
    }
}

it('registers the panel chrome stylesheet with filaments asset system', function () {
    $styles = collect(FilamentAsset::getStyles(['codenzia/filament-panel-base']))
        ->filter(fn (Css $css): bool => $css->getId() === 'panel-base');

    expect($styles)->toHaveCount(1);

    $asset = $styles->first();

    expect($asset->getPackage())->toBe('codenzia/filament-panel-base')
        ->and($asset->getPath())->toEndWith('panel-base.css')
        ->and(file_exists($asset->getPath()))->toBeTrue();
});

it('scopes every rule in the stylesheet under the package prefix', function () {
    $css = preg_replace('#/\*.*?\*/#s', '', fpbStylesheet());

    preg_match_all('/(?:^|[\s,{}])(\.[A-Za-z_][\w-]*)/m', $css, $matches);

    $unscoped = collect($matches[1])
        ->unique()
        ->reject(fn (string $class): bool => str_starts_with($class, '.fpb-') || $class === '.dark' || $class === '.flag')
        ->values()
        ->all();

    expect($unscoped)->toBe([]);
});

it('lays out the visit website button with scoped classes', function () {
    $html = Blade::render('<x-filament-panel-base::visit-website-button />');

    expect($html)
        ->toContain('fpb-visit')
        ->toContain('fpb-visit__icon')
        ->toContain('fpb-visit__label');

    expect(fpbStylesheet())
        ->toContain('.fpb-visit {')
        ->toContain('.fpb-visit__label {');
});

it('lays out the sidebar search with scoped classes', function () {
    $html = Blade::render('<x-filament-panel-base::sidebar-search />');

    expect($html)
        ->toContain('fpb-search')
        ->toContain('fpb-search__field')
        ->toContain('fpb-search__icon')
        ->toContain('fpb-search__clear');
});

it('lays out the panel badge with a scoped class per colour', function () {
    expect(Blade::render('<x-filament-panel-base::panel-badge label="Admin" />'))
        ->toContain('fpb-badge')
        ->not->toContain('fpb-badge--');

    expect(Blade::render('<x-filament-panel-base::panel-badge label="Admin" color="danger" />'))
        ->toContain('fpb-badge--danger');

    expect(Blade::render('<x-filament-panel-base::panel-badge label="Admin" color="nonsense" />'))
        ->toContain('fpb-badge')
        ->not->toContain('fpb-badge--nonsense');
});

it('lays out the dark mode toggle and sidebar collapse button with scoped classes', function () {
    expect(Blade::render('<x-filament-panel-base::dark-mode-toggle />'))
        ->toContain('fpb-dark-toggle')
        ->toContain('fpb-dark-toggle__icon');

    // The collapse button falls back to a raw chevron when no icon alias is
    // configured; Filament's own <x-filament::icon> only compiles once its
    // support provider has registered the `filament` component namespace.
    app()->register(SupportServiceProvider::class);

    expect(Blade::render('<x-filament-panel-base::sidebar-collapse-button />'))
        ->toContain('fpb-collapse')
        ->toContain('fpb-collapse__button--open')
        ->toContain('fpb-collapse__icon--closed');
});

it('defines every scoped class the panel chrome components use', function () {
    // Definitions live either in the packaged stylesheet or, for a view that
    // must also render outside a Filament panel, in the scoped <style> block
    // that view ships itself (the locale switcher).
    $definitions = fpbStylesheet();

    $used = [];

    foreach (glob(__DIR__.'/../resources/views/components/*.blade.php') as $path) {
        $source = file_get_contents($path);
        $definitions .= $source;

        // `(?<!-)` keeps CSS custom properties such as `--fpb-page` out.
        preg_match_all('/(?<!-)fpb-[A-Za-z0-9_-]*/', $source, $matches);

        foreach ($matches[0] as $class) {
            $used[$class] = true;
        }
    }

    expect($used)->not->toBeEmpty();

    // A class built in PHP (`'fpb-badge--'.$color`) is captured with its
    // trailing separator; a definition under that prefix satisfies it.
    $undefined = collect(array_keys($used))
        ->reject(fn (string $class): bool => str_contains($definitions, '.'.$class))
        ->values()
        ->all();

    expect($undefined)->toBe([]);
});
