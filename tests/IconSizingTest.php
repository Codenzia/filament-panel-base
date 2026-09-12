<?php

use Illuminate\Support\Facades\Blade;

/**
 * Icons in this package are rendered into consumer panels that may have no
 * compiled custom theme, where Tailwind sizing utilities such as `h-5 w-5`
 * simply do not exist. Without intrinsic width/height the SVG then expands to
 * fill its container. Every class-sized SVG must therefore carry literal
 * width/height attributes as the fallback.
 */
it('sizes the visit website button icon with width and height attributes', function () {
    $html = Blade::render('<x-filament-panel-base::visit-website-button />');

    expect($html)
        ->toContain('h-5 w-5')
        ->toMatch('/<svg[^>]*\swidth="20"/')
        ->toMatch('/<svg[^>]*\sheight="20"/');
});

it('sizes the panel badge icon with width and height attributes', function () {
    $html = Blade::render(
        '<x-filament-panel-base::panel-badge label="Admin" icon="heroicon-o-sparkles" />'
    );

    expect($html)
        ->toMatch('/<svg[^>]*\swidth="16"/')
        ->toMatch('/<svg[^>]*\sheight="16"/');
});

it('gives social provider icons a 20x20 fallback the caller can override', function () {
    $html = Blade::render('<x-filament-panel-base::social-provider-icon provider="google" />');

    expect($html)
        ->toMatch('/<svg[^>]*\swidth="20"/')
        ->toMatch('/<svg[^>]*\sheight="20"/');

    $overridden = Blade::render(
        '<x-filament-panel-base::social-provider-icon provider="google" class="h-8 w-8" width="32" height="32" />'
    );

    expect($overridden)
        ->toMatch('/<svg[^>]*\swidth="32"/')
        ->toContain('h-8 w-8');
});

it('leaves no package SVG sized by utility classes alone', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../resources', RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());

        if (str_contains($path, '/resources/dist/')) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        $relative = substr($path, strpos($path, '/resources/') + 1);

        preg_match_all('/<svg\b[^>]*>/s', $source, $tags);

        foreach ($tags[0] as $tag) {
            $sizedByClass = (bool) preg_match('/(?:^|[\s"\'{])(?:h|w|size)-[0-9.]+(?:[\s"\'}]|$)/', $tag);
            $hasIntrinsicSize = str_contains($tag, 'width=') && str_contains($tag, 'height=');

            if ($sizedByClass && ! $hasIntrinsicSize) {
                $offenders[] = $relative.': '.trim(preg_replace('/\s+/', ' ', $tag));
            }
        }

        preg_match_all('/@svg\((?:[^()]|\([^()]*\))*\)/', $source, $directives);

        foreach ($directives[0] as $directive) {
            if (! str_contains($directive, "'width'")) {
                $offenders[] = $relative.': '.$directive;
            }
        }
    }

    expect($offenders)->toBe([]);
});
