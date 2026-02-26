<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Contracts;

/**
 * Contract for settings classes that provide theme color values.
 *
 * Implement this on your settings class to feed runtime colors
 * into the <x-panel-base::theme-styles> component and Filament panel colors.
 *
 * @see \Codenzia\FilamentPanelBase\Support\ThemePresets for available presets
 */
interface ProvidesThemeColors
{
    /**
     * Get the resolved theme colors as a flat key-value array.
     *
     * Keys should match ThemePresets::colorKeys() (e.g., 'primary_color', 'danger_color').
     * When a preset is selected, implementations should return that preset's colors
     * merged with any custom overrides.
     *
     * @return array<string, string>
     */
    public function getThemeColors(): array;
}
