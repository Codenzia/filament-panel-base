<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Tests\Support;

use Illuminate\View\Component;

/**
 * Stand-in for Filament's <x-filament::icon> Blade component. The package test
 * suite does not boot a Filament panel, so the real component namespace isn't
 * registered; tests that render a package Livewire view in isolation alias this
 * stub to `filament::icon` so the view compiles.
 */
class FilamentIconStub extends Component
{
    public function render(): string
    {
        return '<span></span>';
    }
}
