<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('Slowest pages') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Top routes by average server duration in the :range.', ['range' => $rangeLabel]) }}
        </x-slot>

        @if (! $ready)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Run php artisan migrate to create the analytics tables.') }}
            </p>
        @elseif ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Not enough timing data yet. Pages need at least 5 samples to appear here.') }}
            </p>
        @else
            @php
                $max = $rows->max('avgMs') ?: 1;
            @endphp
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($rows as $row)
                    <li class="py-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate font-mono text-xs text-gray-700 dark:text-gray-300" title="{{ $row->label }}">
                                {{ \Illuminate\Support\Str::limit($row->label, 60) }}
                            </span>
                            <span class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">
                                {{ number_format($row->avgMs) }}<span class="text-xs text-gray-500"> ms</span>
                            </span>
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="h-1 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div
                                    class="h-full rounded-full {{ $row->avgMs > 1000 ? 'bg-danger-500' : ($row->avgMs > 500 ? 'bg-warning-500' : 'bg-success-500') }}"
                                    style="width: {{ (int) round(($row->avgMs / $max) * 100) }}%"
                                ></div>
                            </div>
                            <span class="text-xs text-gray-500">{{ $row->samples }} samples</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
