<div class="mx-auto w-full max-w-md my-10 sm:my-16 rounded-lg bg-surface-card p-6 shadow-sm ring-1 ring-surface-border dark:bg-surface-card-dark dark:ring-surface-border-dark">
    <header class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            {{ __('filament-panel-base::two-factor.challenge_title') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('filament-panel-base::two-factor.challenge_intro') }}
        </p>
    </header>

    <form wire:submit="submit" class="space-y-4">
        <div>
            <label for="two-factor-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('filament-panel-base::two-factor.code_label') }}
            </label>
            <input wire:model="code"
                id="two-factor-code"
                type="text"
                inputmode="text"
                autocomplete="one-time-code"
                autofocus
                required
                class="mt-1 block w-full rounded-md border border-surface-border bg-surface-input text-center text-lg tracking-[0.25em] shadow-sm focus:border-primary-500 focus:ring-primary-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-surface-border-dark dark:bg-surface-input-dark dark:text-gray-100" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('filament-panel-base::two-factor.code_hint') }}
            </p>
            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" wire:model="rememberDevice"
                class="h-4 w-4 rounded border-surface-border text-primary-600 focus:ring-primary-500" />
            {{ __('filament-panel-base::two-factor.remember_device') }}
        </label>

        <button type="submit"
            class="flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
            {{ __('filament-panel-base::two-factor.submit') }}
        </button>
    </form>
</div>
