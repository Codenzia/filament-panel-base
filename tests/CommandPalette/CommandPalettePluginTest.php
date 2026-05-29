<?php

use Codenzia\FilamentPanelBase\CommandPalette\CommandPalettePlugin;
use Codenzia\FilamentPanelBase\CommandPalette\Settings\CommandPaletteSettings;

beforeEach(function (): void {
    $settings = $this->settingsStub(CommandPaletteSettings::class);
    $settings->enabled = true;
    $settings->track_recent_views = true;
    $settings->recent_view_limit = 10;
    $settings->hotkey_label = 'Ctrl+K';
    app()->instance(CommandPaletteSettings::class, $settings);
});

it('starts disabled until enable() is called', function (): void {
    $plugin = new CommandPalettePlugin;

    expect($plugin->isEnabled())->toBeFalse();
    $plugin->enable();
    expect($plugin->isEnabled())->toBeTrue();
});

it('clamps recent view limit between 1 and 50', function (): void {
    expect((new CommandPalettePlugin)->recentViewLimit(0)->getOverrides()['recent_view_limit'])->toBe(1);
    expect((new CommandPalettePlugin)->recentViewLimit(100)->getOverrides()['recent_view_limit'])->toBe(50);
    expect((new CommandPalettePlugin)->recentViewLimit(15)->getOverrides()['recent_view_limit'])->toBe(15);
});

it('applies overrides to settings singleton when enabled', function (): void {
    (new CommandPalettePlugin)
        ->enable()
        ->trackRecentViews(false)
        ->recentViewLimit(20)
        ->hotkeyLabel('⌘K')
        ->apply();

    $settings = app(CommandPaletteSettings::class);
    expect($settings->track_recent_views)->toBeFalse();
    expect($settings->recent_view_limit)->toBe(20);
    expect($settings->hotkey_label)->toBe('⌘K');
});

it('does not apply overrides when not enabled', function (): void {
    (new CommandPalettePlugin)->hotkeyLabel('Foo')->apply();

    expect(app(CommandPaletteSettings::class)->hotkey_label)->toBe('Ctrl+K');
});
