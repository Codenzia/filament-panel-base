{{--
    "Powered by Codenzia" credit line for non-Filament pages.

    Drop into the root layout of any front-of-site (Livewire, Blade, or
    raw HTML) page that lives outside a Filament panel. Filament panels
    already get the same footer via the PanelsRenderHook::FOOTER hook
    registered in FilamentPanelBaseServiceProvider — do not double-include.

    Usage:
        <x-filament-panel-base::powered-by />

    Hide via env:
        CODENZIA_BRANDING=false
--}}
@if (config('filament-panel-base.branding.powered_by_enabled', true))
    <div class="py-3 text-center text-xs text-gray-400 dark:text-gray-600">
        Powered by
        <a href="https://www.codenzia.com" target="_blank" rel="noopener"
           class="font-medium hover:text-primary-500 transition">Codenzia</a>
    </div>
@endif
