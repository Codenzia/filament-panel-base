<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Support\ThemePresets;

it('contains all expected presets', function () {
    $presets = ThemePresets::all();

    expect($presets)->toHaveCount(18);
    expect($presets)->toHaveKeys([
        'ocean_blue', 'forest_green', 'sunset_orange', 'royal_purple',
        'rose_garden', 'modern_dark', 'teal_breeze', 'amber_gold',
        'slate_steel', 'crimson_fire', 'sky_light', 'emerald_fresh',
        'indigo_classic', 'pink_blossom', 'warm_earth', 'midnight_blue',
        'charcoal_noir', 'custom',
    ]);
});

it('returns ocean_blue as defaults', function () {
    $defaults = ThemePresets::defaults();

    expect($defaults['label'])->toBe('Ocean Blue');
    expect($defaults['primary_color'])->toBe('#3b82f6');
});

it('ensures all non-custom presets have all required color keys', function () {
    $requiredKeys = ThemePresets::colorKeys();

    foreach (ThemePresets::all() as $slug => $preset) {
        if ($slug === 'custom') {
            continue;
        }

        foreach ($requiredKeys as $key) {
            expect($preset)->toHaveKey($key, "Preset '{$slug}' is missing key '{$key}'");
        }
    }
});

it('custom preset has only a label', function () {
    $custom = ThemePresets::get('custom');

    expect($custom)->toBe(['label' => 'Custom']);
});

it('returns labels for all presets', function () {
    $labels = ThemePresets::labels();

    expect($labels)->toHaveCount(18);
    expect($labels['ocean_blue'])->toBe('Ocean Blue');
    expect($labels['custom'])->toBe('Custom');
});

it('returns null for unknown preset', function () {
    expect(ThemePresets::get('nonexistent'))->toBeNull();
});

it('returns the correct color keys list', function () {
    $keys = ThemePresets::colorKeys();

    expect($keys)->toContain('primary_color');
    expect($keys)->toContain('danger_color');
    expect($keys)->toContain('shadow_color');
    expect($keys)->toHaveCount(15);
});
