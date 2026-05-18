{{-- Sign in / Sign up topbar buttons.
     Injected via USER_MENU_BEFORE render hook in BasePanelProvider when ->showAuthLinks($loginPanel, $registerPanel) is set.
     Renders only for unauthenticated visitors.
     Props:
       $loginPanel    — Filament panel ID providing the login route (e.g. 'user').
       $registerPanel — Filament panel ID providing the registration route; null hides "Sign up". --}}

@props([
    'loginPanel' => null,
    'registerPanel' => null,
])

@guest
    @php
        $loginUrl = $loginPanel
            ? rescue(fn () => \Filament\Facades\Filament::getPanel($loginPanel)->getLoginUrl(), null, false)
            : null;
        $registerUrl = $registerPanel
            ? rescue(fn () => \Filament\Facades\Filament::getPanel($registerPanel)->getRegistrationUrl(), null, false)
            : null;
    @endphp

    <div class="flex items-center gap-2">
        @if ($loginUrl)
            <a href="{{ $loginUrl }}"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white transition-colors">
                {{ __('Sign in') }}
            </a>
        @endif

        @if ($registerUrl)
            <a href="{{ $registerUrl }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-500 hover:to-primary-400 rounded-lg shadow-sm shadow-primary-500/25 transition-all duration-200 hover:shadow-md hover:shadow-primary-500/30">
                @svg('heroicon-o-sparkles', 'h-4 w-4')
                <span>{{ __('Sign up free') }}</span>
            </a>
        @endif
    </div>
@endguest
