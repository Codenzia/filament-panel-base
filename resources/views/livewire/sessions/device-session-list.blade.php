<div class="space-y-4">
    @if (! $driverOk)
        <div class="rounded-md bg-gray-100 p-3 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-200">
            <p class="font-medium">{{ __('filament-panel-base::sessions.driver_required_heading') }}</p>
            <p class="mt-1 text-xs">{{ __('filament-panel-base::sessions.driver_required_detail') }}</p>
        </div>
    @elseif ($sessions->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-panel-base::sessions.empty') }}
        </p>
    @else
        @if ($allowLogoutOthers && $sessions->count() > 1)
            <div class="flex justify-end">
                <button type="button"
                    wire:click="logoutOtherDevices"
                    wire:confirm="{{ __('filament-panel-base::sessions.logout_others_confirm') }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                    <x-filament::icon icon="heroicon-o-arrow-right-on-rectangle" class="h-4 w-4" />
                    {{ __('filament-panel-base::sessions.logout_others_button') }}
                </button>
            </div>
        @endif

        <ul class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($sessions as $session)
                @php
                    $minutesIdle = $session->lastActivity->diffInMinutes(now());
                    $isIdle = $minutesIdle > $idleThresholdMinutes;
                @endphp
                <li class="py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <x-filament::icon
                                :icon="match ($session->deviceType) {
                                    'mobile' => 'heroicon-o-device-phone-mobile',
                                    'tablet' => 'heroicon-o-device-tablet',
                                    default => 'heroicon-o-computer-desktop',
                                }"
                                class="mt-0.5 h-5 w-5 shrink-0 text-gray-500" />
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $session->label() }}
                                    @if ($session->isCurrent)
                                        <span class="ml-1 rounded-full bg-primary-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                            {{ __('filament-panel-base::sessions.this_device') }}
                                        </span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $session->ipAddress ?: __('filament-panel-base::sessions.unknown_ip') }}
                                    &middot;
                                    @if ($isIdle)
                                        <span class="text-gray-400">{{ __('filament-panel-base::sessions.last_active', ['time' => $session->lastActivity->diffForHumans()]) }}</span>
                                    @else
                                        <span class="text-primary-600 dark:text-primary-400">{{ __('filament-panel-base::sessions.active_now') }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <button type="button"
                            wire:click="revoke('{{ $session->id }}')"
                            wire:confirm="{{ $session->isCurrent ? __('filament-panel-base::sessions.revoke_current_confirm') : __('filament-panel-base::sessions.revoke_confirm') }}"
                            class="shrink-0 rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                            {{ $session->isCurrent ? __('filament-panel-base::sessions.sign_out') : __('filament-panel-base::sessions.revoke') }}
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
