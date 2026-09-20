<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Providers\BasePanelProvider;
use Filament\Panel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/**
 * The chrome this package injects through render hooks — the topbar buttons and
 * switchers, the sidebar search box, the branding footer and the user-menu
 * chips — was written with English literals or with `__()` keys the package
 * never supplied Arabic for, so a fully translated panel still read
 * "Visit Website", "Search menu...", "Powered by Codenzia", "Edit Profile",
 * "No phone" and a raw Spatie role slug.
 */
class PanelChromeTestProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel;
    }

    public function renderVisitWebsite(): string
    {
        return $this->getVisitWebsiteButton()->render();
    }

    public function roleLabelFor(mixed $role): string
    {
        return $this->resolveRoleLabel($role);
    }
}

beforeEach(function (): void {
    Route::get('/locale/{locale}', fn () => '')->name('locale.switch');
    app()->setLocale('ar');
});

afterEach(fn () => app()->setLocale('en'));

it('translates the topbar Visit Website button', function (): void {
    expect(__('Visit Website'))->toBe('زيارة الموقع');

    expect((new PanelChromeTestProvider(app()))->renderVisitWebsite())
        ->toContain('زيارة الموقع')
        ->not->toContain('Visit Website');
});

it('translates the sidebar search placeholder', function (): void {
    $html = Blade::render('<x-filament-panel-base::sidebar-search />');

    expect($html)
        ->toContain('ابحث في القائمة...')
        ->not->toContain('Search menu...');
});

it('translates the Powered by credit while keeping the Codenzia name', function (): void {
    $html = Blade::render('<x-filament-panel-base::powered-by />');

    expect($html)
        ->toContain('مدعوم بواسطة')
        ->not->toContain('Powered by')
        ->toContain('>Codenzia</a>');
});

it('translates the language switcher label', function (): void {
    $html = Blade::render(
        '<x-filament-panel-base::locale-switcher :locales="$l" current-locale="ar" />',
        ['l' => ['en' => ['native' => 'English'], 'ar' => ['native' => 'العربية']]],
    );

    expect($html)->toContain('تغيير اللغة')->not->toContain('Change language');
});

it('translates the dark mode toggle titles', function (): void {
    $html = Blade::render('<x-filament-panel-base::dark-mode-toggle />');

    expect($html)->toContain('الوضع الفاتح')->toContain('الوضع الداكن');
});

it('translates the user menu profile chips', function (): void {
    expect(__('Edit Profile'))->toBe('تعديل الملف الشخصي')
        ->and(__('No phone'))->toBe('لا يوجد هاتف')
        ->and(__('No Email'))->not->toBe('No Email')
        ->and(__('Personal Information'))->not->toBe('Personal Information');
});

it('translates a known role slug for the role chip', function (): void {
    $provider = new PanelChromeTestProvider(app());

    expect($provider->roleLabelFor('vendor'))->toBe('بائع')
        ->and($provider->roleLabelFor('super_admin'))->toBe('مشرف عام');
});

it('prefers the application catalogue over the package role names', function (): void {
    app('translator')->addLines(['roles.vendor' => 'شريك البيع'], 'ar');

    expect((new PanelChromeTestProvider(app()))->roleLabelFor('vendor'))->toBe('شريك البيع');
});

it('prefers a role model that carries its own label', function (): void {
    $role = new class
    {
        public string $name = 'vendor';

        public function getLabel(): string
        {
            return 'تاجر معتمد';
        }
    };

    expect((new PanelChromeTestProvider(app()))->roleLabelFor($role))->toBe('تاجر معتمد');
});

it('prints an unknown role slug exactly as it is stored', function (): void {
    $role = new class
    {
        public string $name = 'regional_supervisor';
    };

    expect((new PanelChromeTestProvider(app()))->roleLabelFor($role))->toBe('regional_supervisor');
});

it('leaves the English chrome untouched', function (): void {
    app()->setLocale('en');

    expect(__('Visit Website'))->toBe('Visit Website')
        ->and(__('Search menu...'))->toBe('Search menu...')
        ->and(__('Powered by'))->toBe('Powered by')
        ->and(__('Edit Profile'))->toBe('Edit Profile')
        ->and((new PanelChromeTestProvider(app()))->roleLabelFor('vendor'))->toBe('Vendor');
});
