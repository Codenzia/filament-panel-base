<div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
    <header class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            {{ __('filament-panel-base::auth.verify_otp_title', ['channel' => $channelLabel]) }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('filament-panel-base::auth.verify_otp_intro', ['length' => $length, 'target' => $target]) }}
        </p>
    </header>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ session('status') }}</div>
    @endif

    <form wire:submit="verify" class="space-y-4">
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Verification code') }}</label>
            <input wire:model="code" id="code" type="text" inputmode="numeric" pattern="\d*" maxlength="{{ $length }}" autocomplete="one-time-code" required
                class="mt-1 block w-full rounded-md border-gray-300 text-center text-lg tracking-[0.5em] shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
            {{ __('filament-panel-base::auth.verify_otp_submit') }}
        </button>
    </form>

    <button type="button" wire:click="resend" class="mt-4 block w-full text-center text-sm font-medium text-primary-600 hover:text-primary-700">
        {{ __('filament-panel-base::auth.verify_otp_resend') }}
    </button>
</div>
