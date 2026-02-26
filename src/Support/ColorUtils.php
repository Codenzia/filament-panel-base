<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Support;

class ColorUtils
{
    /**
     * Convert a hex color code to RGB array.
     *
     * @param  string  $hex  The hex color code (e.g., '#3b82f6' or '#fff')
     * @return array<int> Array of [red, green, blue] values (0-255)
     */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        // Handle shorthand hex (e.g., '#fff' -> '#ffffff')
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Convert a hex color code to RGB string format.
     *
     * @param  string  $hex  The hex color code (e.g., '#3b82f6')
     * @return string RGB string (e.g., 'rgb(59, 130, 246)')
     */
    public static function hexToRgbString(string $hex): string
    {
        $rgb = self::hexToRgb($hex);

        return "rgb({$rgb[0]}, {$rgb[1]}, {$rgb[2]})";
    }

    /**
     * Convert a hex color code to rgba string with opacity.
     *
     * @param  string  $hex  The hex color code
     * @param  float  $alpha  The alpha value (0.0 - 1.0)
     * @return string RGBA string (e.g., 'rgba(59, 130, 246, 0.5)')
     */
    public static function hexToRgba(string $hex, float $alpha): string
    {
        $rgb = self::hexToRgb($hex);

        return "rgba({$rgb[0]}, {$rgb[1]}, {$rgb[2]}, {$alpha})";
    }

    /**
     * Check if a hex color is light or dark.
     *
     * @param  string  $hex  The hex color code
     * @return bool True if the color is light, false if dark
     */
    public static function isLightColor(string $hex): bool
    {
        $rgb = self::hexToRgb($hex);
        // Calculate relative luminance
        $luminance = (0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2]) / 255;

        return $luminance > 0.5;
    }

    /**
     * Get the contrasting text color (black or white) for a background color.
     *
     * @param  string  $hex  The hex color code of the background
     * @return string The hex color code for text (#000000 or #ffffff)
     */
    public static function getContrastColor(string $hex): string
    {
        return self::isLightColor($hex) ? '#000000' : '#ffffff';
    }
}
