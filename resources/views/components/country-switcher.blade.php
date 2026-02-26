{{-- Country Switcher Navbar Dropdown
     Props: $mode (show|disabled|hide), $switchRoute (route name)
     Reads: $availableCountries, $currentCountry (view-shared by SetCountry middleware) --}}

@props([
    'mode' => 'show',
    'switchRoute' => 'country.switch',
])

@if ($mode !== 'hide' && isset($availableCountries) && $availableCountries->count() > 0)
    <div class="relative" x-data="{ open: false }">
        <button @if ($mode !== 'disabled') @click="open = !open" @endif
            @disabled($mode === 'disabled')
            class="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 px-2 py-1 rounded-md {{ $mode === 'disabled' ? 'cursor-not-allowed opacity-60' : '' }}">
            @if ($currentCountry?->code)
                <span class="fi fi-{{ strtolower($currentCountry->code) }} shrink-0"></span>
            @else
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            @endif
            <span>{{ $currentCountry?->name ?? __('Select Country') }}</span>
        </button>
        @if ($mode !== 'disabled')
            <div x-show="open" x-cloak @click.away="open = false" x-transition
                class="absolute end-0 mt-2 w-48 bg-white dark:bg-gray-700 rounded-md shadow-lg border dark:border-gray-600 z-50 max-h-64 overflow-y-auto">
                @foreach ($availableCountries as $country)
                    <a href="{{ route($switchRoute, $country) }}"
                        class="flex items-center gap-2 px-4 py-2 text-sm {{ $currentCountry && $currentCountry->id === $country->id ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                        <span class="fi fi-{{ strtolower($country->code) }} shrink-0"></span>
                        {{ $country->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
