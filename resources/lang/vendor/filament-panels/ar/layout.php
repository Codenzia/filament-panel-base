<?php

declare(strict_types=1);

/*
 * Minimal Filament-panels layout override so the <html dir="..."> attribute
 * flips to RTL whenever Arabic is the active locale. Filament reads
 * `__('filament-panels::layout.direction')` in its base layout — this file
 * is the entire bridge. Only ships `direction` (the rest of the keys are
 * inherited from `filament/translations` when installed, or stay English).
 */
return [
    'direction' => 'rtl',
];
