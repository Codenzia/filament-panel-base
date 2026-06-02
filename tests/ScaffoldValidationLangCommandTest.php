<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->scaffoldRoot = lang_path();

    // Make sure we don't trample anything from earlier runs.
    foreach (['ar', 'fr', 'de'] as $locale) {
        $path = lang_path("{$locale}/validation.php");
        if (File::exists($path)) {
            File::delete($path);
        }
    }
});

it('scaffolds validation.php for declared locales from config', function () {
    config(['filament-panel-base.locale.available' => ['ar', 'fr']]);

    $this->artisan('filament-panel-base:scaffold-validation')
        ->expectsOutputToContain('Done.')
        ->assertSuccessful();

    expect(File::exists(lang_path('ar/validation.php')))->toBeTrue()
        ->and(File::exists(lang_path('fr/validation.php')))->toBeTrue();

    $arContents = File::get(lang_path('ar/validation.php'));
    expect($arContents)->toContain("'required' =>");
});

it('respects explicit locale arguments over config', function () {
    config(['filament-panel-base.locale.available' => ['ar']]);

    $this->artisan('filament-panel-base:scaffold-validation', ['locales' => ['de']])
        ->assertSuccessful();

    expect(File::exists(lang_path('de/validation.php')))->toBeTrue()
        ->and(File::exists(lang_path('ar/validation.php')))->toBeFalse();
});

it('skips existing files unless --force is set', function () {
    File::ensureDirectoryExists(lang_path('ar'));
    File::put(lang_path('ar/validation.php'), "<?php return ['marker' => 'original'];");

    $this->artisan('filament-panel-base:scaffold-validation', ['locales' => ['ar']])
        ->assertSuccessful();

    expect(File::get(lang_path('ar/validation.php')))->toContain("'marker' => 'original'");

    $this->artisan('filament-panel-base:scaffold-validation', ['locales' => ['ar'], '--force' => true])
        ->assertSuccessful();

    expect(File::get(lang_path('ar/validation.php')))->toContain("'required' =>");
});
