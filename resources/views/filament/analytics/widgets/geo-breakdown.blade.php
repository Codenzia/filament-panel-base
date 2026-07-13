<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('Top countries') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Visitor origin by country in the :range.', ['range' => $rangeLabel]) }}
        </x-slot>

        @if (! $ready)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Run php artisan migrate to create the analytics tables.') }}
            </p>
        @elseif ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('No country-tagged visits in the last 7 days. Configure a Country model so SetCountry middleware can resolve visitor origin.') }}
            </p>
        @else
            @php
                $max = $rows->max('views') ?: 1;
            @endphp
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($rows as $row)
                    <li class="py-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="inline-flex items-center gap-2">
                                <span class="flag flag-{{ strtolower($row->country) }}"
                                      style="width: 1.4em; height: 1em;"
                                      title="{{ $row->country }}"></span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $row->country }}
                                </span>
                            </span>
                            <span class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-gray-100">
                                {{ number_format($row->views) }}
                            </span>
                        </div>
                        <div class="mt-1 h-1 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
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
