{{-- Currency Switcher Navbar Dropdown
     Props: $switchRoute (route name)
     Reads: $availableCurrencies, $currentCurrency, $currentCurrencyMode (view-shared by SetCurrency middleware) --}}

@props([
    'switchRoute' => 'currency.switch',
])

@if (isset($availableCurrencies) && count($availableCurrencies) > 1)
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open"
            class="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 px-2 py-1 rounded-md">
            <span class="font-semibold">{{ $currentCurrency?->symbol ?? '$' }}</span>
            <span class="hidden sm:inline">{{ $currentCurrency?->code ?? 'USD' }}</span>
        </button>
        <div x-show="open" x-cloak @click.away="open = false" x-transition
            class="absolute end-0 mt-2 w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg border dark:border-gray-600 z-50 max-h-64 overflow-y-auto">
            @foreach ($availableCurrencies as $currency)
                @if ($currency->is_auto ?? false)
                    {{-- Auto/Default for country option --}}
                    <a href="{{ route($switchRoute, 'auto') }}"
                        class="flex items-center gap-2 px-4 py-2 text-sm {{ $currentCurrencyMode === 'auto' ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                        <span class="text-xs text-gray-400">{{ __('Auto') }}</span>
                        <span class="truncate">{{ $currency->title }}</span>
                    </a>
                @else
                    {{-- Regular currency option --}}
                    <a href="{{ route($switchRoute, $currency->id) }}"
                        class="flex items-center gap-2 px-4 py-2 text-sm {{ $currentCurrencyMode == $currency->id ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                        <span class="font-semibold w-5">{{ $currency->symbol }}</span>
                        <span>{{ $currency->code }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif
