<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View as ViewFactory;

/**
 * Wraps an auth page in the host's configured layout.
 *
 * Livewire has two ways of putting a page inside a layout and they are not
 * interchangeable. `->layout()` renders the layout as a Blade component and
 * passes the page in as `$slot`; `->extends()` renders it as a template the
 * page fills a named `@section` of. Pointing the component-style call at a
 * section-based layout does not fail — it renders the host's whole page shell
 * around an empty content section, so the visitor gets a sign-in page with no
 * sign-in form and nothing in the log.
 *
 * Hosts whose layout is section-based name the section in
 * `filament-panel-base.auth.layout_section` and get `@extends` instead. Hosts
 * that name a layout the application does not ship get the bundled fallback
 * and a warning, rather than a 500 on their only route into the account area.
 */
trait ResolvesAuthLayout
{
    private const FALLBACK_LAYOUT = 'filament-panel-base::layouts.auth';

    protected function withAuthLayout(View $view): View
    {
        $layout = $this->resolveAuthLayout();

        if ($layout === self::FALLBACK_LAYOUT) {
            return $view->layout($layout);
        }

        $section = config('filament-panel-base.auth.layout_section');

        if (is_string($section) && $section !== '') {
            return $view->extends($layout)->section($section);
        }

        return $view->layout($layout);
    }

    private function resolveAuthLayout(): string
    {
        $configured = config('filament-panel-base.auth.layout');

        if (! is_string($configured) || $configured === '') {
            return self::FALLBACK_LAYOUT;
        }

        if (ViewFactory::exists($configured)) {
            return $configured;
        }

        Log::warning('filament-panel-base: the configured auth layout does not exist; using the bundled one.', [
            'configured' => $configured,
            'used' => self::FALLBACK_LAYOUT,
        ]);

        return self::FALLBACK_LAYOUT;
    }
}
