<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Support;

/**
 * Predefined theme color palettes for Codenzia applications.
 *
 * Each preset contains 15 color keys that define a complete visual look.
 * The 'custom' sentinel has only a label and is used when projects define
 * their own colors outside any preset.
 */
class ThemePresets
{
    /**
     * All available presets, keyed by slug.
     *
     * @var array<string, array<string, string>>
     */
    public const PRESETS = [
        'ocean_blue' => [
            'label' => 'Ocean Blue',
            'primary_color' => '#3b82f6',
            'primary_hover_color' => '#2563eb',
            'secondary_color' => '#64748b',
            'secondary_hover_color' => '#475569',
            'background_color' => '#ffffff',
            'surface_color' => '#f8fafc',
            'text_primary_color' => '#1e293b',
            'text_secondary_color' => '#64748b',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#3b82f6',
            'border_color' => '#e2e8f0',
            'shadow_color' => 'rgba(0, 0, 0, 0.1)',
        ],
        'forest_green' => [
            'label' => 'Forest Green',
            'primary_color' => '#16a34a',
            'primary_hover_color' => '#15803d',
            'secondary_color' => '#737373',
            'secondary_hover_color' => '#525252',
            'background_color' => '#ffffff',
            'surface_color' => '#f0fdf4',
            'text_primary_color' => '#14532d',
            'text_secondary_color' => '#4b5563',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#eab308',
            'danger_color' => '#dc2626',
            'info_color' => '#0ea5e9',
            'border_color' => '#d1d5db',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'sunset_orange' => [
            'label' => 'Sunset Orange',
            'primary_color' => '#ea580c',
            'primary_hover_color' => '#c2410c',
            'secondary_color' => '#78716c',
            'secondary_hover_color' => '#57534e',
            'background_color' => '#ffffff',
            'surface_color' => '#fff7ed',
            'text_primary_color' => '#431407',
            'text_secondary_color' => '#57534e',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#16a34a',
            'warning_color' => '#d97706',
            'danger_color' => '#dc2626',
            'info_color' => '#0284c7',
            'border_color' => '#e7e5e4',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'royal_purple' => [
            'label' => 'Royal Purple',
            'primary_color' => '#7c3aed',
            'primary_hover_color' => '#6d28d9',
            'secondary_color' => '#6b7280',
            'secondary_hover_color' => '#4b5563',
            'background_color' => '#ffffff',
            'surface_color' => '#faf5ff',
            'text_primary_color' => '#2e1065',
            'text_secondary_color' => '#6b7280',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#8b5cf6',
            'border_color' => '#e5e7eb',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'rose_garden' => [
            'label' => 'Rose Garden',
            'primary_color' => '#e11d48',
            'primary_hover_color' => '#be123c',
            'secondary_color' => '#71717a',
            'secondary_hover_color' => '#52525b',
            'background_color' => '#ffffff',
            'surface_color' => '#fff1f2',
            'text_primary_color' => '#4c0519',
            'text_secondary_color' => '#71717a',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#06b6d4',
            'border_color' => '#e4e4e7',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'modern_dark' => [
            'label' => 'Modern Dark',
            'primary_color' => '#6366f1',
            'primary_hover_color' => '#4f46e5',
            'secondary_color' => '#a1a1aa',
            'secondary_hover_color' => '#71717a',
            'background_color' => '#0f172a',
            'surface_color' => '#1e293b',
            'text_primary_color' => '#f8fafc',
            'text_secondary_color' => '#94a3b8',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#4ade80',
            'warning_color' => '#fbbf24',
            'danger_color' => '#f87171',
            'info_color' => '#818cf8',
            'border_color' => '#334155',
            'shadow_color' => 'rgba(0, 0, 0, 0.3)',
        ],
        'teal_breeze' => [
            'label' => 'Teal Breeze',
            'primary_color' => '#0d9488',
            'primary_hover_color' => '#0f766e',
            'secondary_color' => '#64748b',
            'secondary_hover_color' => '#475569',
            'background_color' => '#ffffff',
            'surface_color' => '#f0fdfa',
            'text_primary_color' => '#134e4a',
            'text_secondary_color' => '#64748b',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#06b6d4',
            'border_color' => '#e2e8f0',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'amber_gold' => [
            'label' => 'Amber Gold',
            'primary_color' => '#d97706',
            'primary_hover_color' => '#b45309',
            'secondary_color' => '#78716c',
            'secondary_hover_color' => '#57534e',
            'background_color' => '#ffffff',
            'surface_color' => '#fffbeb',
            'text_primary_color' => '#451a03',
            'text_secondary_color' => '#78716c',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#16a34a',
            'warning_color' => '#ea580c',
            'danger_color' => '#dc2626',
            'info_color' => '#0284c7',
            'border_color' => '#e7e5e4',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'slate_steel' => [
            'label' => 'Slate Steel',
            'primary_color' => '#475569',
            'primary_hover_color' => '#334155',
            'secondary_color' => '#94a3b8',
            'secondary_hover_color' => '#64748b',
            'background_color' => '#ffffff',
            'surface_color' => '#f8fafc',
            'text_primary_color' => '#0f172a',
            'text_secondary_color' => '#64748b',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#3b82f6',
            'border_color' => '#cbd5e1',
            'shadow_color' => 'rgba(0, 0, 0, 0.1)',
        ],
        'crimson_fire' => [
            'label' => 'Crimson Fire',
            'primary_color' => '#dc2626',
            'primary_hover_color' => '#b91c1c',
            'secondary_color' => '#78716c',
            'secondary_hover_color' => '#57534e',
            'background_color' => '#ffffff',
            'surface_color' => '#fef2f2',
            'text_primary_color' => '#450a0a',
            'text_secondary_color' => '#78716c',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#16a34a',
            'warning_color' => '#d97706',
            'danger_color' => '#9f1239',
            'info_color' => '#0284c7',
            'border_color' => '#e7e5e4',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'sky_light' => [
            'label' => 'Sky Light',
            'primary_color' => '#0284c7',
            'primary_hover_color' => '#0369a1',
            'secondary_color' => '#6b7280',
            'secondary_hover_color' => '#4b5563',
            'background_color' => '#ffffff',
            'surface_color' => '#f0f9ff',
            'text_primary_color' => '#0c4a6e',
            'text_secondary_color' => '#6b7280',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#38bdf8',
            'border_color' => '#e5e7eb',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'emerald_fresh' => [
            'label' => 'Emerald Fresh',
            'primary_color' => '#059669',
            'primary_hover_color' => '#047857',
            'secondary_color' => '#6b7280',
            'secondary_hover_color' => '#4b5563',
            'background_color' => '#ffffff',
            'surface_color' => '#ecfdf5',
            'text_primary_color' => '#064e3b',
            'text_secondary_color' => '#6b7280',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#06b6d4',
            'border_color' => '#e5e7eb',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'indigo_classic' => [
            'label' => 'Indigo Classic',
            'primary_color' => '#4f46e5',
            'primary_hover_color' => '#4338ca',
            'secondary_color' => '#6b7280',
            'secondary_hover_color' => '#4b5563',
            'background_color' => '#ffffff',
            'surface_color' => '#eef2ff',
            'text_primary_color' => '#1e1b4b',
            'text_secondary_color' => '#6b7280',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#6366f1',
            'border_color' => '#e5e7eb',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'pink_blossom' => [
            'label' => 'Pink Blossom',
            'primary_color' => '#db2777',
            'primary_hover_color' => '#be185d',
            'secondary_color' => '#71717a',
            'secondary_hover_color' => '#52525b',
            'background_color' => '#ffffff',
            'surface_color' => '#fdf2f8',
            'text_primary_color' => '#500724',
            'text_secondary_color' => '#71717a',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#22c55e',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#ec4899',
            'border_color' => '#e4e4e7',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'warm_earth' => [
            'label' => 'Warm Earth',
            'primary_color' => '#92400e',
            'primary_hover_color' => '#78350f',
            'secondary_color' => '#78716c',
            'secondary_hover_color' => '#57534e',
            'background_color' => '#ffffff',
            'surface_color' => '#fefce8',
            'text_primary_color' => '#422006',
            'text_secondary_color' => '#78716c',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#16a34a',
            'warning_color' => '#d97706',
            'danger_color' => '#dc2626',
            'info_color' => '#0284c7',
            'border_color' => '#e7e5e4',
            'shadow_color' => 'rgba(0, 0, 0, 0.08)',
        ],
        'midnight_blue' => [
            'label' => 'Midnight Blue',
            'primary_color' => '#1d4ed8',
            'primary_hover_color' => '#1e40af',
            'secondary_color' => '#64748b',
            'secondary_hover_color' => '#475569',
            'background_color' => '#0f172a',
            'surface_color' => '#1e293b',
            'text_primary_color' => '#e2e8f0',
            'text_secondary_color' => '#94a3b8',
            'text_on_primary_color' => '#ffffff',
            'success_color' => '#4ade80',
            'warning_color' => '#fbbf24',
            'danger_color' => '#f87171',
            'info_color' => '#60a5fa',
            'border_color' => '#334155',
            'shadow_color' => 'rgba(0, 0, 0, 0.3)',
        ],
        'charcoal_noir' => [
            'label' => 'Charcoal Noir',
            'primary_color' => '#f59e0b',
            'primary_hover_color' => '#d97706',
            'secondary_color' => '#9ca3af',
            'secondary_hover_color' => '#6b7280',
            'background_color' => '#111827',
            'surface_color' => '#1f2937',
            'text_primary_color' => '#f9fafb',
            'text_secondary_color' => '#9ca3af',
            'text_on_primary_color' => '#000000',
            'success_color' => '#34d399',
            'warning_color' => '#fb923c',
            'danger_color' => '#f87171',
            'info_color' => '#38bdf8',
            'border_color' => '#374151',
            'shadow_color' => 'rgba(0, 0, 0, 0.4)',
        ],
        'custom' => [
            'label' => 'Custom',
        ],
    ];

    /**
     * Get all presets including the 'custom' sentinel.
     *
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return self::PRESETS;
    }

    /**
     * Get a single preset by key, or null if not found.
     *
     * @return array<string, string>|null
     */
    public static function get(string $key): ?array
    {
        return self::PRESETS[$key] ?? null;
    }

    /**
     * Get a key => label map for use in Select dropdowns.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(
            fn (array $preset): string => $preset['label'],
            self::PRESETS,
        );
    }

    /**
     * Get the default preset (ocean_blue) colors.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return self::PRESETS['ocean_blue'];
    }

    /**
     * Get color keys that every non-custom preset must define.
     *
     * @return list<string>
     */
    public static function colorKeys(): array
    {
        return [
            'primary_color',
            'primary_hover_color',
            'secondary_color',
            'secondary_hover_color',
            'background_color',
            'surface_color',
            'text_primary_color',
            'text_secondary_color',
            'text_on_primary_color',
            'success_color',
            'warning_color',
            'danger_color',
            'info_color',
            'border_color',
            'shadow_color',
        ];
    }
}
