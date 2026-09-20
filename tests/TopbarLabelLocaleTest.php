<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Providers\BasePanelProvider;
use Filament\Panel;
use Illuminate\Support\Facades\Lang;

/**
 * A panel provider is configured while the container registers, long before any
 * locale middleware has run, so a label built with `__()` at that point freezes
 * in the application's default language. These cover the closure form, which is
 * called as the topbar renders and therefore follows the reader's locale.
 */
class TopbarLabelTestProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel;
    }

    public function renderBadge(): string
    {
        return $this->getPanelBadge()->render();
    }

    public function renderVisitWebsite(): string
    {
        return $this->getVisitWebsiteButton()->render();
    }
}

beforeEach(function (): void {
    Lang::addLines(['panel.crew' => 'Operators', 'panel.home' => 'Home'], 'en');
    Lang::addLines(['panel.crew' => 'المشغّلون', 'panel.home' => 'الرئيسية'], 'ar');
});

afterEach(fn () => app()->setLocale('en'));

it('renders a closure title badge in the locale active at render time', function (): void {
    $provider = (new TopbarLabelTestProvider(app()))
        ->addTitleBadge(fn (): string => __('panel.crew'));

    app()->setLocale('en');
    expect($provider->renderBadge())->toContain('Operators');

    // The provider is not rebuilt — only the locale changed, exactly as it does
    // between two requests to the same panel.
    app()->setLocale('ar');
    expect($provider->renderBadge())
        ->toContain('المشغّلون')
        ->not->toContain('Operators');
});

it('renders a closure Visit Website label in the locale active at render time', function (): void {
    $provider = (new TopbarLabelTestProvider(app()))
        ->showVisitWebsite(true, fn (): string => __('panel.home'));

    app()->setLocale('en');
    expect($provider->renderVisitWebsite())->toContain('Home');

    app()->setLocale('ar');
    expect($provider->renderVisitWebsite())
        ->toContain('الرئيسية')
        ->not->toContain('>Home<');
});

it('still accepts a plain string badge label and runs it through the catalogue', function (): void {
    $provider = (new TopbarLabelTestProvider(app()))->addTitleBadge('panel.crew');

    app()->setLocale('ar');

    expect($provider->renderBadge())->toContain('المشغّلون');
});

it('still accepts a plain string Visit Website label verbatim', function (): void {
    $provider = (new TopbarLabelTestProvider(app()))->showVisitWebsite(true, 'Back to site');

    expect($provider->renderVisitWebsite())->toContain('Back to site');
});

it('falls back to the translated default when no Visit Website label is given', function (): void {
    $provider = (new TopbarLabelTestProvider(app()))->showVisitWebsite();

    expect($provider->renderVisitWebsite())->toContain(__('Visit Website'));
});
