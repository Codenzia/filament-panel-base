<div class="mx-auto w-full max-w-md my-10 sm:my-16 rounded-lg bg-surface-card p-6 shadow-sm ring-1 ring-surface-border dark:bg-surface-card-dark dark:ring-surface-border-dark">
    <header class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('filament-panel-base::auth.forgot_title') }}</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('filament-panel-base::auth.forgot_subtitle') }}</p>
    </header>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ session('status') }}</div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('filament-panel-base::auth.email') }}</label>
            <input wire:model="email" id="email" type="email" autocomplete="email" required
                class="mt-1 block w-full rounded-md border border-surface-border bg-surface-input shadow-sm focus:border-primary-500 focus:ring-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark dark:bg-surface-input-dark dark:text-gray-100" />
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-primary-700">
            {{ __('filament-panel-base::auth.forgot_submit') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
        <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700">{{ __('filament-panel-base::auth.sign_in') }}</a>
    </p>
</div>
