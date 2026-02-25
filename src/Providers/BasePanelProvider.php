<?php

namespace Codenzia\FilamentPanelBase\Providers;

use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Codenzia\FilamentPanelBase\Support\ColorUtils;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

abstract class BasePanelProvider extends PanelProvider
{
    /**
     * Apply shared configuration (branding, colors, user menu, render hooks) to a panel.
     */
    protected function configureSharedSettings(Panel $panel): Panel
    {
        return $panel
            ->brandName(fn (): string => $this->resolveBrandName())
            ->brandLogo(fn (): ?string => $this->resolveBrandLogo())
            ->brandLogoHeight('2.5rem')
            ->favicon(fn (): ?string => $this->resolveFavicon())
            ->colors(fn (): array => $this->getColorsFromSettings())
            ->userMenuItems($this->getUserMenuItems($panel))
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): HtmlString => $this->getVisitWebsiteButton(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): HtmlString => $this->getPanelBadge($panel),
            )
            ->sidebarCollapsibleOnDesktop();
    }

    /**
     * Resolve the brand name from settings or config.
     */
    protected function resolveBrandName(): string
    {
        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings && property_exists($settings, 'app_name')) {
            return $settings->app_name;
        }

        return config('app.name', 'Laravel');
    }

    /**
     * Resolve the brand logo URL from settings.
     */
    protected function resolveBrandLogo(): ?string
    {
        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings && method_exists($settings, 'getAppLogoUrl')) {
            return $settings->getAppLogoUrl();
        }

        return null;
    }

    /**
     * Resolve the favicon URL from settings.
     */
    protected function resolveFavicon(): ?string
    {
        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings && method_exists($settings, 'getAppFaviconUrl')) {
            return $settings->getAppFaviconUrl();
        }

        return null;
    }

    protected function getColorsFromSettings(): array
    {
        $settings = FilamentPanelBasePlugin::make()->resolveSettings();

        if ($settings) {
            return $this->getColorsFromSettingsInstance($settings);
        }

        return $this->getColorsFromConfig();
    }

    /**
     * Build color array from a settings instance that has color properties.
     */
    protected function getColorsFromSettingsInstance(object $settings): array
    {
        $colors = [];
        $colorNames = ['primary', 'secondary', 'danger', 'warning', 'success', 'info'];

        foreach ($colorNames as $name) {
            $property = "{$name}_color";
            if (property_exists($settings, $property) && $settings->{$property}) {
                $rgb = ColorUtils::hexToRgb($settings->{$property});
                $colors[$name] = Color::rgb("rgb({$rgb[0]}, {$rgb[1]}, {$rgb[2]})");
            }
        }

        $colors['gray'] = Color::Slate;

        return $colors;
    }

    /**
     * Build color array from config values.
     */
    protected function getColorsFromConfig(): array
    {
        $configColors = config('filament-panel-base.colors', []);
        $colors = [];

        foreach ($configColors as $name => $hex) {
            $rgb = ColorUtils::hexToRgb($hex);
            $colors[$name] = Color::rgb("rgb({$rgb[0]}, {$rgb[1]}, {$rgb[2]})");
        }

        $colors['gray'] = Color::Slate;

        return $colors;
    }

    /**
     * Build the user menu items shared across all panels.
     * Includes: user name label, profile link, cross-panel navigation links.
     */
    protected function getUserMenuItems(Panel $panel): array
    {
        $panelId = $panel->getId();
        $panels = config('filament-panel-base.panels', ['admin', 'dashboard']);

        $items = [
            // Show user name as the top item (non-clickable label)
            'profile' => fn (Action $action) => $action
                ->url(null)
                ->label(fn (): string => filament()->getUserName(filament()->auth()->user())),

            // Profile edit link
            Action::make('edit-profile')
                ->label(__('Edit Profile'))
                ->icon('heroicon-o-user')
                ->url(fn (): string => filament()->getProfileUrl())
                ->sort(-1),
            Action::make('um_role')
                ->disabled()
                ->icon('heroicon-c-book-open')
                ->label(fn () => filament()->auth()->user()?->roles->pluck('name')->join(', ') ?? __('User')),
            Action::make('um_phone')
                ->disabled()
                ->icon('heroicon-o-phone')
                ->label(fn () => filament()->auth()->user()?->phone ?? __('No phone')),
            Action::make('um_email')
                ->disabled()
                ->icon('heroicon-o-envelope')
                ->label(fn () => filament()->auth()->user()?->email ?? __('No Email')),
        ];

        // Cross-panel navigation
        if (count($panels) >= 2) {
            $dashboardPanel = $panels[1] ?? 'dashboard';
            $adminPanel = $panels[0] ?? 'admin';

            if ($panelId === $dashboardPanel) {
                $items[] = Action::make('admin-panel')
                    ->label(__('Admin Panel'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url('/' . $adminPanel)
                    ->color('info')
                    ->visible(fn (): bool => Auth::user()?->canAccessPanel(filament()->getPanel($adminPanel)) ?? false)
                    ->sort(50);
            } elseif ($panelId === $adminPanel) {
                $items[] = Action::make('user-dashboard')
                    ->label(__('My Dashboard'))
                    ->icon('heroicon-o-squares-2x2')
                    ->url('/' . $dashboardPanel)
                    ->color('primary')
                    ->visible(fn (): bool => Auth::user()?->canAccessPanel(filament()->getPanel($dashboardPanel)) ?? false)
                    ->sort(50);
            }
        }

        return $items;
    }

    /**
     * Get a small badge identifying the current panel.
     */
    protected function getPanelBadge(Panel $panel): HtmlString
    {
        $panelId = $panel->getId();
        $panels = config('filament-panel-base.panels', ['admin', 'dashboard']);
        $adminPanel = $panels[0] ?? 'admin';

        if ($panelId === $adminPanel) {
            $label = __('Administration');
            $classes = 'bg-indigo-100 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-400/30';
        } else {
            $label = __('My Account');
            $classes = 'bg-primary-100 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/30';
        }

        return new HtmlString(
            '<span style="margin-left: 1rem;" class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset ' . $classes . '">' . $label . '</span>'
        );
    }

    protected function getVisitWebsiteButton(): HtmlString
    {
        $label = __('Visit Website');

        return new HtmlString('
            <a href="/" target="_blank" class="fi-btn fi-btn-color-gray fi-btn-size-md fi-color-gray gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/10" title="' . $label . '">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
                <span class="hidden sm:inline">' . $label . '</span>
            </a>
        ');
    }

    /**
     * Get the shared middleware stack used by all panels.
     */
    protected function getSharedMiddleware(): array
    {
        return [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Filament\Http\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Filament\Http\Middleware\DisableBladeIconComponents::class,
            \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
        ];
    }
}
