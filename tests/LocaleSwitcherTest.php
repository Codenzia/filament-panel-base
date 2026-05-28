<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/locale/{locale}', fn () => '')->name('locale.switch');
});

it('renders self-contained markup with no flag-icons dependency by default', function () {
    $html = Blade::render(
        '<x-filament-panel-base::locale-switcher :locales="$l" current-locale="en" />',
        ['l' => ['en' => ['native' => 'English'], 'ar' => ['native' => 'العربية']]],
    );

    expect($html)
        ->toContain('fpb-ls__trigger')          // scoped class, not raw Tailwind
        ->toContain('fpb-ls__globe')             // globe glyph, not a flag sprite
        ->not->toContain('class="flag flag-')    // no flag-icons by default
        ->toContain('English')
        ->toContain('العربية')
        ->toContain('/locale/ar');
});

it('marks the active locale with aria-current', function () {
    $html = Blade::render(
        '<x-filament-panel-base::locale-switcher :locales="$l" current-locale="ar" />',
        ['l' => ['en' => [], 'ar' => []]],
    );

    expect($html)->toMatch('/lang="ar"[^>]*aria-current="true"/');
});

it('renders flag sprites only when flags are explicitly enabled', function () {
    $html = Blade::render(
        '<x-filament-panel-base::locale-switcher :locales="$l" :flags="true" />',
        ['l' => ['en' => [], 'ar' => []]],
    );

    expect($html)->toContain('flag flag-gb'); // en → gb
    expect($html)->toContain('flag flag-sa'); // ar → sa
});

it('does not render with a single locale', function () {
    $html = Blade::render(
        '<x-filament-panel-base::locale-switcher :locales="$l" />',
        ['l' => ['en' => []]],
    );

    // The scoped <style> is always emitted; assert the dropdown BUTTON/links aren't.
    expect($html)
        ->not->toContain('aria-haspopup="true"')
        ->not->toContain('/locale/');
});

it('fills missing native names from the built-in map', function () {
    $html = Blade::render(
        '<x-filament-panel-base::locale-switcher :locales="$l" />',
        ['l' => ['en' => [], 'fr' => []]],
    );

    expect($html)->toContain('Français');
});
