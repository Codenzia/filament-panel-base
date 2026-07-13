<div class="rounded-md border border-warning-300 bg-warning-50 p-3 dark:border-warning-700 dark:bg-warning-900">
    <p class="mb-2 text-sm font-medium text-warning-800 dark:text-warning-200">
        {{ __('filament-panel-base::two-factor.recovery_codes_heading') }}
    </p>
    <p class="mb-3 text-xs text-warning-700 dark:text-warning-300">
        {{ __('filament-panel-base::two-factor.recovery_codes_warning') }}
    </p>
    <ul class="grid grid-cols-2 gap-2 font-mono text-xs text-gray-900 dark:text-gray-100">
        @foreach ($recoveryCodes as $code)
            <li class="rounded bg-white px-2 py-1 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">{{ $code }}</li>
        @endforeach
    </ul>
</div>
