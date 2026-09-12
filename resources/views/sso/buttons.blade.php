{{-- "Continue with ..." buttons for every configured OIDC provider.

     Self-resolving: it asks the ProviderRegistry for the provider list rather
     than taking it as data, so both include sites (the package's Livewire
     login page and the Filament panel render hook) stay one-liners and can
     never show a different set of buttons from one another.

     Renders nothing at all when SSO is off or no provider is fully
     configured. --}}

@php
    $ssoProviders = app(\Codenzia\FilamentPanelBase\Sso\ProviderRegistry::class)->all();
@endphp

@if (! empty($ssoProviders))
    <div class="my-6 flex items-center gap-3">
        <hr class="flex-1 border-surface-border dark:border-surface-border-dark" />
        <span class="text-xs text-gray-500">{{ __('filament-panel-base::auth.or_continue_with') }}</span>
        <hr class="flex-1 border-surface-border dark:border-surface-border-dark" />
    </div>

    <div class="space-y-2">
        @foreach ($ssoProviders as $ssoProvider)
            <a href="{{ route('filament-panel-base.sso.redirect', ['provider' => $ssoProvider->key]) }}"
               class="flex w-full items-center justify-center gap-2 rounded-md border border-surface-border bg-surface-input px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark dark:bg-surface-input-dark dark:text-gray-200 dark:hover:bg-gray-800">
                <x-filament-panel-base::social-provider-icon :provider="$ssoProvider->icon ?? $ssoProvider->key" />
                <span>{{ __('filament-panel-base::auth.continue_with', ['provider' => $ssoProvider->label]) }}</span>
            </a>
        @endforeach
    </div>
@endif
