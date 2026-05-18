<div class="mx-auto w-full max-w-md my-10 sm:my-16 rounded-lg bg-surface-card p-6 shadow-sm ring-1 ring-surface-border dark:bg-surface-card-dark dark:ring-surface-border-dark">
    <header class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('filament-panel-base::auth.login_title') }}</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('filament-panel-base::auth.login_subtitle') }}</p>
    </header>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ session('error') }}</div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="identifier" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ match ($credentialsMode) {
                    'phone' => __('filament-panel-base::auth.identifier_phone'),
                    'both' => __('filament-panel-base::auth.identifier_either'),
                    default => __('filament-panel-base::auth.identifier_email'),
                } }}
            </label>
            <input wire:model="identifier" id="identifier" type="text" autocomplete="username" required
                class="mt-1 block w-full rounded-md border border-surface-border bg-surface-input shadow-sm focus:border-primary-500 focus:ring-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark dark:bg-surface-input-dark dark:text-gray-100" />
            @error('identifier') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('filament-panel-base::auth.password') }}</label>
                <a href="{{ route('password.request') }}" class="text-sm text-primary-600 hover:text-primary-700">{{ __('filament-panel-base::auth.forgot_password') }}</a>
            </div>
            <input wire:model="password" id="password" type="password" autocomplete="current-password" required
                class="mt-1 block w-full rounded-md border border-surface-border bg-surface-input shadow-sm focus:border-primary-500 focus:ring-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark dark:bg-surface-input-dark dark:text-gray-100" />
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center text-sm text-gray-700 dark:text-gray-300">
            <input wire:model="remember" type="checkbox" class="rounded border border-surface-border text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark" />
            <span class="ms-2">{{ __('filament-panel-base::auth.remember_me') }}</span>
        </label>

        <button type="submit" class="flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
            {{ __('filament-panel-base::auth.login_submit') }}
        </button>
    </form>

    @if (! empty($enabledSocialProviders))
        <div class="my-6 flex items-center gap-3">
            <hr class="flex-1 border-gray-200 dark:border-gray-700" />
            <span class="text-xs text-gray-500">{{ __('filament-panel-base::auth.or_continue_with') }}</span>
            <hr class="flex-1 border-gray-200 dark:border-gray-700" />
        </div>
        <div class="space-y-2">
            @foreach ($enabledSocialProviders as $provider)
                <a href="{{ route('oauth.redirect', $provider) }}" class="flex w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                    <x-filament-panel-base::social-provider-icon :provider="$provider" />
                    <span>{{ __('filament-panel-base::auth.continue_with', ['provider' => ucfirst($provider)]) }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
        {{ __('filament-panel-base::auth.no_account_yet') }}
        <a href="{{ route('register') }}" class="font-medium text-primary-600 hover:text-primary-700">{{ __('filament-panel-base::auth.create_account') }}</a>
    </p>
</div>
