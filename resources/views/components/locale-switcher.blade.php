{{-- Language/Locale Switcher Navbar Dropdown
     Props: $locales (array from Language::getActive()), $currentLocale (string), $switchRoute (route name) --}}

@props([
    'locales' => [],
    'currentLocale' => app()->getLocale(),
    'switchRoute' => 'locale.switch',
])

@if (count($locales) > 1)
    @php
        // Map language codes to country codes for flag-icons
        $langToFlag = [
            'en' => 'gb', 'ar' => 'sa', 'zh' => 'cn', 'ja' => 'jp', 'ko' => 'kr',
            'hi' => 'in', 'ur' => 'pk', 'fa' => 'ir', 'he' => 'il', 'ms' => 'my',
            'vi' => 'vn', 'sv' => 'se', 'da' => 'dk', 'cs' => 'cz', 'el' => 'gr',
            'uk' => 'ua', 'bn' => 'bd', 'ta' => 'lk', 'sw' => 'ke',
        ];
    @endphp
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open"
            class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 px-2 py-1 rounded-md">
            <span class="fi fi-{{ $langToFlag[$currentLocale] ?? $currentLocale }} shrink-0"></span>
            <span class="font-semibold">{{ strtoupper($currentLocale) }}</span>
        </button>
        <div x-show="open" x-cloak @click.away="open = false" x-transition
            class="absolute end-0 mt-2 w-44 bg-white dark:bg-gray-700 rounded-md shadow-lg border dark:border-gray-600 z-50">
            @foreach ($locales as $code => $locale)
                <a href="{{ route($switchRoute, $code) }}"
                    class="flex items-center gap-2 px-4 py-2 text-sm {{ $code === $currentLocale ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                    <span class="fi fi-{{ $langToFlag[$code] ?? $code }} shrink-0"></span>
                    <span class="font-semibold">{{ strtoupper($code) }}</span>
                    <span class="text-xs text-gray-400">{{ $locale['native'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
