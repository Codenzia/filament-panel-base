{{--
    Drop-in 419 session-expiry handler for front-of-site Livewire pages that
    render OUTSIDE a Filament panel (e.g. the package's own login/register
    views, which extend the host's layout). Filament panel pages already get
    this automatically via the BODY_END render hook.

    Usage in a host layout:
        <x-filament-panel-base::session-expiry-handler />
--}}
@include('filament-panel-base::session-expiry.script', [
    'redirectUrl' => \Codenzia\FilamentPanelBase\Support\SessionExpiry::redirectUrl(),
])
