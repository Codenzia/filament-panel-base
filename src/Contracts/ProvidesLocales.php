<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Contracts;

/**
 * Contract for models/classes that provide active locale information.
 *
 * Implement this interface on your Language model to use the SetLocale middleware.
 */
interface ProvidesLocales
{
    /**
     * Get active locales as an associative array.
     *
     * @return array<string, array{name: string, native: string, dir: string, flag: string}>
     */
    public static function getActive(): array;
}
