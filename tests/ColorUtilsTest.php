<?php

use Codenzia\FilamentPanelBase\Support\ColorUtils;

it('converts 6-digit hex to RGB array', function () {
    expect(ColorUtils::hexToRgb('#3b82f6'))->toBe([59, 130, 246])
        ->and(ColorUtils::hexToRgb('#000000'))->toBe([0, 0, 0])
        ->and(ColorUtils::hexToRgb('#ffffff'))->toBe([255, 255, 255])
        ->and(ColorUtils::hexToRgb('#ef4444'))->toBe([239, 68, 68]);
});

it('converts 3-digit shorthand hex to RGB array', function () {
    expect(ColorUtils::hexToRgb('#fff'))->toBe([255, 255, 255])
        ->and(ColorUtils::hexToRgb('#000'))->toBe([0, 0, 0])
        ->and(ColorUtils::hexToRgb('#f00'))->toBe([255, 0, 0]);
});

it('handles hex without hash prefix', function () {
    expect(ColorUtils::hexToRgb('3b82f6'))->toBe([59, 130, 246])
        ->and(ColorUtils::hexToRgb('fff'))->toBe([255, 255, 255]);
});

it('converts hex to RGB string', function () {
    expect(ColorUtils::hexToRgbString('#3b82f6'))->toBe('rgb(59, 130, 246)')
        ->and(ColorUtils::hexToRgbString('#000000'))->toBe('rgb(0, 0, 0)')
        ->and(ColorUtils::hexToRgbString('#ffffff'))->toBe('rgb(255, 255, 255)');
});

it('converts hex to RGBA string with alpha', function () {
    expect(ColorUtils::hexToRgba('#3b82f6', 0.5))->toBe('rgba(59, 130, 246, 0.5)')
        ->and(ColorUtils::hexToRgba('#000000', 1.0))->toBe('rgba(0, 0, 0, 1)')
        ->and(ColorUtils::hexToRgba('#ffffff', 0.0))->toBe('rgba(255, 255, 255, 0)');
});

it('detects light colors', function () {
    expect(ColorUtils::isLightColor('#ffffff'))->toBeTrue()
        ->and(ColorUtils::isLightColor('#f0f0f0'))->toBeTrue()
        ->and(ColorUtils::isLightColor('#ffff00'))->toBeTrue();
});

it('detects dark colors', function () {
    expect(ColorUtils::isLightColor('#000000'))->toBeFalse()
        ->and(ColorUtils::isLightColor('#1a1a1a'))->toBeFalse()
        ->and(ColorUtils::isLightColor('#3b82f6'))->toBeFalse();
});

it('returns correct contrast color for light backgrounds', function () {
    expect(ColorUtils::getContrastColor('#ffffff'))->toBe('#000000')
        ->and(ColorUtils::getContrastColor('#f0f0f0'))->toBe('#000000');
});

it('returns correct contrast color for dark backgrounds', function () {
    expect(ColorUtils::getContrastColor('#000000'))->toBe('#ffffff')
        ->and(ColorUtils::getContrastColor('#3b82f6'))->toBe('#ffffff');
});
