<div class="mx-auto w-full max-w-md my-10 sm:my-16 rounded-lg bg-surface-card p-6 shadow-sm ring-1 ring-surface-border dark:bg-surface-card-dark dark:ring-surface-border-dark">
    <header class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('filament-panel-base::auth.reset_title') }}</h2>
    </header>

    <form wire:submit="resetPassword" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('filament-panel-base::auth.email') }}</label>
            <input wire:model="email" id="email" type="email" autocomplete="email" required
                class="mt-1 block w-full rounded-md border border-surface-border bg-surface-input shadow-sm focus:border-primary-500 focus:ring-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark dark:bg-surface-input-dark dark:text-gray-100" />
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('filament-panel-base::auth.password') }}</label>
            <input wire:model="password" id="password" type="password" autocomplete="new-password" required
                class="mt-1 block w-full rounded-md border border-surface-border bg-surface-input shadow-sm focus:border-primary-500 focus:ring-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark dark:bg-surface-input-dark dark:text-gray-100" />
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('filament-panel-base::auth.password_confirmation') }}</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" required
                class="mt-1 block w-full rounded-md border border-surface-border bg-surface-input shadow-sm focus:border-primary-500 focus:ring-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark dark:bg-surface-input-dark dark:text-gray-100" />
        </div>

        <button type="submit" class="flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-primary-700">
            {{ __('filament-panel-base::auth.reset_submit') }}
        </button>
    </form>
</div>
