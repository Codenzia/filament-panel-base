{{-- Currency Switcher Navbar Dropdown
     Props: $switchRoute (route name), $align (start|end), $relative (bool)
     Reads: $availableCurrencies, $currentCurrency, $currentCurrencyMode (view-shared by SetCurrency middleware) --}}

@props([
    'switchRoute' => 'currency.switch',
    'align' => 'end',
    'relative' => true,
])

@if (isset($availableCurrencies) && count($availableCurrencies) > 1)
    <div class="fpb-switcher {{ $relative ? 'fpb-switcher--relative relative' : '' }}" x-data="{ open: false }">
        <button @click="open = !open"
            class="fpb-switcher__trigger flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 px-2 py-1 rounded-md">
            <span class="fpb-switcher__symbol font-semibold">{{ $currentCurrency?->symbol ?? '$' }}</span>
            <span class="fpb-switcher__code hidden sm:inline">{{ $currentCurrency?->code ?? 'USD' }}</span>
        </button>
        <div x-show="open" x-cloak @click.away="open = false" x-transition
            class="fpb-switcher__menu fpb-switcher__menu--tall fpb-switcher__menu--{{ $align === 'start' ? 'start' : 'end' }} absolute {{ $align === 'start' ? 'start-0' : 'end-0' }} mt-2 w-48 z-50 bg-white dark:bg-gray-700 rounded-md shadow-lg border border-gray-300 dark:border-gray-600 max-h-96 overflow-y-auto py-1">
            @foreach ($availableCurrencies as $currency)
                @if ($currency->is_auto ?? false)
                    {{-- Auto/Default for country option --}}
                    <a href="{{ route($switchRoute, 'auto') }}"
                        class="fpb-switcher__item flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-zinc-300 dark:hover:bg-gray-600">
                        @if ($currentCurrencyMode === 'auto')
                            <svg class="fpb-switcher__check w-3 h-3 text-brand-600 dark:text-brand-400 shrink-0" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        @else
                            <span class="fpb-switcher__check-placeholder w-3 shrink-0"></span>
                        @endif
                        <span class="fpb-switcher__item-hint text-xs text-gray-400">{{ fpb_trans('Auto') }}</span>
                        <span class="fpb-switcher__item-label truncate">{{ $currency->title }}</span>
                    </a>
                @else
                    {{-- Regular currency option --}}
                    <a href="{{ route($switchRoute, $currency->id) }}"
                        class="fpb-switcher__item flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-zinc-300 dark:hover:bg-gray-600">
                        @if ($currentCurrencyMode == $currency->id)
                            <svg class="fpb-switcher__check w-3 h-3 text-brand-600 dark:text-brand-400 shrink-0" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        @else
                            <span class="fpb-switcher__check-placeholder w-3 shrink-0"></span>
                        @endif
                        <span class="fpb-switcher__item-symbol font-semibold w-5">{{ $currency->symbol }}</span>
                        <span>{{ $currency->code }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif
