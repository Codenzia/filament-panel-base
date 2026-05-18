<div class="space-y-6">
    <header>
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('filament-panel-base::auth.social_manage_title') }}</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('filament-panel-base::auth.social_manage_subtitle') }}</p>
    </header>

    @if (session('status'))
        <div class="rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ session('error') }}</div>
    @endif

    @if ($connectedAccounts->isNotEmpty())
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('filament-panel-base::auth.social_connected') }}</h4>
            <ul class="divide-y divide-gray-200 rounded-md border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                @foreach ($connectedAccounts as $account)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-filament-panel-base::social-provider-icon :provider="$account->provider" />
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($account->provider) }}</p>
                                @if ($account->email)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $account->email }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($canDisconnect($account))
                            <button type="button" wire:click="disconnect({{ $account->id }})" wire:confirm="{{ __('filament-panel-base::auth.social_disconnect_confirm', ['provider' => ucfirst($account->provider)]) }}" class="text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400">
                                {{ __('filament-panel-base::auth.social_disconnect') }}
                            </button>
                        @else
                            <span class="text-xs text-gray-400" title="{{ __('filament-panel-base::auth.social_disconnect_blocked') }}">
                                {{ __('filament-panel-base::auth.social_disconnect_locked') }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($availableProviders))
        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('filament-panel-base::auth.social_available') }}</h4>
            <div class="space-y-2">
                @foreach ($availableProviders as $provider)
                    <a href="{{ route('oauth.redirect', ['provider' => $provider, 'link' => 1, 'return_to' => url()->current()]) }}" class="flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        <x-filament-panel-base::social-provider-icon :provider="$provider" />
                        <span>{{ __('filament-panel-base::auth.social_connect', ['provider' => ucfirst($provider)]) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($connectedAccounts->isEmpty() && empty($availableProviders))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament-panel-base::auth.social_none_configured') }}</p>
    @endif
</div>
