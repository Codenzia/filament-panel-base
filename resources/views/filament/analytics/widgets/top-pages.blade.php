<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('Top pages') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Most-visited routes in the :range.', ['range' => $rangeLabel]) }}
        </x-slot>

        @if (! $ready)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Run php artisan migrate to create the analytics tables.') }}
            </p>
        @elseif ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('No page views recorded in the last 7 days.') }}
            </p>
        @else
            @php
                $max = $rows->max('views') ?: 1;
            @endphp
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($rows as $row)
                    <li class="py-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="truncate font-mono text-xs text-gray-700 dark:text-gray-300" title="{{ $row->label }}">
                                {{ \Illuminate\Support\Str::limit($row->label, 60) }}
                            </span>
                            <span class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">
                                {{ number_format($row->views) }}
                            </span>
                        </div>
                        <div class="mt-1 h-1 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                            <div
                                class="h-full rounded-full bg-primary-500"
                                style="width: {{ (int) round(($row->views / $max) * 100) }}%"
                            ></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
