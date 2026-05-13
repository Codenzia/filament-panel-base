<div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
    <header class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('filament-panel-base::auth.verify_email_title') }}</h2>
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            @if ($verified)
                {{ __('filament-panel-base::auth.verify_email_done') }}
            @else
                {{ __('filament-panel-base::auth.verify_email_intro', ['email' => $email]) }}
            @endif
        </p>
    </header>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ session('status') }}</div>
    @endif

    @unless ($verified)
        <button type="button" wire:click="resend" class="flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-primary-700">
            {{ __('filament-panel-base::auth.verify_email_resend') }}
        </button>
    @endunless
</div>
