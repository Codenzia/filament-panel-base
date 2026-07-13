@php
    $state = $state ?? 'disabled';
@endphp

@if ($state === 'unavailable')
    <div class="rounded-md bg-warning-50 p-4 text-sm text-warning-800 dark:bg-warning-900 dark:text-warning-200">
        {{ __('filament-panel-base::two-factor.user_trait_missing') }}
    </div>
@elseif ($state === 'disabled')
    <div class="space-y-3">
        <p class="text-sm text-gray-700 dark:text-gray-300">
            {{ __('filament-panel-base::two-factor.tab_intro_disabled') }}
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('filament-panel-base::two-factor.tab_intro_apps') }}
        </p>
    </div>
@elseif ($state === 'pending')
    <div class="space-y-5">
        <div class="rounded-md bg-info-50 p-3 text-sm text-info-800 dark:bg-info-900 dark:text-info-200">
            {{ __('filament-panel-base::two-factor.pending_intro') }}
        </div>

        <div class="flex flex-col items-center gap-3 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="bg-white p-2 rounded">
                {!! $qrSvg ?? '' !!}
            </div>
            <div class="w-full">
                <p class="mb-1 text-xs font-medium text-gray-600 dark:text-gray-400">
                    {{ __('filament-panel-base::two-factor.manual_key_label') }}
                </p>
                <code class="block break-all rounded bg-gray-100 px-3 py-2 font-mono text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $manualKey ?? '' }}</code>
            </div>
        </div>

        @if (! empty($recoveryCodes ?? []))
            @include('filament-panel-base::filament.two-factor.partials.recovery-codes', ['recoveryCodes' => $recoveryCodes])
        @endif
    </div>
@elseif ($state === 'enabled')
    <div class="space-y-4">
        <div class="flex items-start gap-3 rounded-md bg-success-50 p-3 text-sm text-success-800 dark:bg-success-900 dark:text-success-200">
            <x-filament::icon icon="heroicon-o-shield-check" class="h-5 w-5 shrink-0" />
            <div>
                <p class="font-medium">{{ __('filament-panel-base::two-factor.enabled_status') }}</p>
                <p class="mt-1 text-xs">
                    {{ __('filament-panel-base::two-factor.enabled_status_detail', ['count' => $recoveryCount ?? 0]) }}
                </p>
            </div>
        </div>

        @if (! empty($recoveryCodes ?? []))
            @include('filament-panel-base::filament.two-factor.partials.recovery-codes', ['recoveryCodes' => $recoveryCodes])
        @endif
    </div>
@endif
