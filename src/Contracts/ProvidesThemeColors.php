<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Contracts;

/**
 * Contract for settings classes that provide theme color values.
 *
 * Implement this on your settings class to feed runtime colors
 * into the <x-filament-panel-base::theme-styles> component and Filament panel colors.
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
     * Optional auth-page surface overrides (used by the shipped Livewire auth views):
     * 'surface_page_color', 'surface_page_dark_color',
     * 'surface_card_color', 'surface_card_dark_color',
     * 'surface_input_color', 'surface_input_dark_color',
     * 'surface_border_color', 'surface_border_dark_color'.
     * Omit them to inherit the gray defaults; presets do not need to define them.
     *
     * @return array<string, string>
     */
    public function getThemeColors(): array;
}
