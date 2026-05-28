{{-- Language / locale switcher navbar dropdown.

     Self-contained: ships its own scoped CSS (emitted once) so it renders
     correctly inside a Filament panel AND on any public Blade page — without
     depending on the host's Tailwind build compiling these classes, and
     without requiring the flag-icons library.

     Props:
       $locales       array  — [code => ['native' => ..., 'dir' => ...]] (or bare ['en','ar'])
       $currentLocale string — active locale code (defaults to app locale)
       $switchRoute   string — named route to switch locale (default 'locale.switch')
       $align         string — 'start' | 'end' (default 'end')
       $relative      bool   — wrap in a relatively-positioned container (default true)
       $flags         bool   — render flag-icons sprites instead of the globe glyph.
                               Only enable when the host actually loads flag-icons CSS.
--}}

@props([
    'locales' => [],
    'currentLocale' => null,
    'switchRoute' => 'locale.switch',
    'align' => 'end',
    'relative' => true,
    'flags' => false,
])

@php
    $currentLocale ??= app()->getLocale();

    // Native language names used when a locale entry doesn't carry its own.
    $fpbNatives = [
        'en' => 'English', 'ar' => 'العربية', 'fr' => 'Français', 'es' => 'Español',
        'de' => 'Deutsch', 'tr' => 'Türkçe', 'ur' => 'اردو', 'fa' => 'فارسی',
        'hi' => 'हिन्दी', 'zh' => '中文', 'ru' => 'Русский', 'pt' => 'Português',
        'it' => 'Italiano', 'id' => 'Bahasa Indonesia', 'ms' => 'Bahasa Melayu',
        'he' => 'עברית', 'nl' => 'Nederlands', 'ja' => '日本語', 'ko' => '한국어',
    ];

    // Code → country for flag-icons (only used when $flags is true).
    $fpbFlag = [
        'en' => 'gb', 'ar' => 'sa', 'zh' => 'cn', 'ja' => 'jp', 'ko' => 'kr',
        'hi' => 'in', 'ur' => 'pk', 'fa' => 'ir', 'he' => 'il', 'ms' => 'my',
        'vi' => 'vn', 'sv' => 'se', 'da' => 'dk', 'cs' => 'cz', 'el' => 'gr',
        'uk' => 'ua', 'bn' => 'bd', 'ta' => 'lk', 'sw' => 'ke',
    ];

    // Normalize entries → [code => ['native' => ..., 'dir' => ...]].
    $fpbLocales = [];
    foreach ($locales as $key => $meta) {
        $code = is_int($key) ? (string) $meta : (string) $key;
        $native = is_array($meta) ? ($meta['native'] ?? ($meta['name'] ?? null)) : null;
        if (! $native || $native === $code) {
            $native = $fpbNatives[$code] ?? strtoupper($code);
        }
        $dir = is_array($meta) ? ($meta['dir'] ?? null) : null;
        $dir ??= in_array($code, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
        $fpbLocales[$code] = ['native' => $native, 'dir' => $dir];
    }
@endphp

@once
    <style>
        [x-cloak] { display: none !important; }
        .fpb-ls { position: relative; display: inline-block; }
        .fpb-ls__trigger {
            display: inline-flex; align-items: center; gap: 0.375rem;
            height: 2.25rem; padding: 0 0.625rem; cursor: pointer;
            border: 1px solid rgb(226 232 240); border-radius: 0.5rem;
            background: transparent; color: rgb(71 85 105);
            font-size: 0.8125rem; font-weight: 600; line-height: 1;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .fpb-ls__trigger:hover { background: rgb(241 245 249); color: rgb(15 23 42); }
        .fpb-ls__globe { width: 1rem; height: 1rem; opacity: .8; }
        .fpb-ls__chevron { width: .85rem; height: .85rem; opacity: .6; transition: transform .15s ease; }
        .fpb-ls[data-open="true"] .fpb-ls__chevron { transform: rotate(180deg); }
        .fpb-ls__menu {
            position: absolute; top: calc(100% + 0.5rem); z-index: 50;
            min-width: 11rem; padding: 0.375rem; background: #fff;
            border: 1px solid rgb(226 232 240); border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgba(2, 6, 23, 0.12);
        }
        .fpb-ls__menu--end { inset-inline-end: 0; }
        .fpb-ls__menu--start { inset-inline-start: 0; }
        .fpb-ls__item {
            display: flex; align-items: center; gap: 0.5rem; width: 100%;
            padding: 0.5rem 0.625rem; border-radius: 0.5rem;
            font-size: 0.875rem; color: rgb(51 65 85); text-decoration: none;
            transition: background-color .12s ease;
        }
        .fpb-ls__item:hover { background: rgb(241 245 249); }
        .fpb-ls__item[aria-current="true"] { color: rgb(5 150 105); font-weight: 600; }
        .fpb-ls__check { width: 1rem; height: 1rem; flex: none; }
        .fpb-ls__check--hidden { visibility: hidden; }
        .fpb-ls__native { flex: 1; }
        .fpb-ls__code { font-size: 0.6875rem; letter-spacing: .04em; color: rgb(148 163 184); text-transform: uppercase; }
        .fpb-ls .flag { flex: none; }

        .dark .fpb-ls__trigger { border-color: rgb(51 65 85); color: rgb(203 213 225); }
        .dark .fpb-ls__trigger:hover { background: rgb(30 41 59); color: rgb(241 245 249); }
        .dark .fpb-ls__menu { background: rgb(15 23 42); border-color: rgb(51 65 85); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .dark .fpb-ls__item { color: rgb(203 213 225); }
        .dark .fpb-ls__item:hover { background: rgb(30 41 59); }
        .dark .fpb-ls__item[aria-current="true"] { color: rgb(52 211 153); }
        .dark .fpb-ls__code { color: rgb(100 116 139); }
    </style>
@endonce

@if (count($fpbLocales) > 1)
    <div class="fpb-ls {{ $relative ? '' : '' }}"
         x-data="{ open: false }"
         x-bind:data-open="open.toString()"
         @keydown.escape="open = false">
        <button type="button"
                class="fpb-ls__trigger"
                @click="open = !open"
                x-bind:aria-expanded="open.toString()"
                aria-haspopup="true"
                aria-label="{{ __('Change language') }}">
            @if ($flags)
                <span class="flag flag-{{ $fpbFlag[$currentLocale] ?? $currentLocale }}"></span>
            @else
                <svg class="fpb-ls__globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path stroke-linecap="round" d="M3 12h18M12 3c2.5 2.5 2.5 15 0 18M12 3c-2.5 2.5-2.5 15 0 18"/>
                </svg>
            @endif
            <span>{{ strtoupper($currentLocale) }}</span>
            <svg class="fpb-ls__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>

        <div class="fpb-ls__menu fpb-ls__menu--{{ $align === 'start' ? 'start' : 'end' }}"
             x-show="open"
             x-cloak
             x-transition.origin.top
             @click.outside="open = false"
             role="menu">
            @foreach ($fpbLocales as $code => $locale)
                <a href="{{ route($switchRoute, $code) }}"
                   class="fpb-ls__item"
                   role="menuitem"
                   lang="{{ $code }}"
                   dir="{{ $locale['dir'] }}"
                   @if ($code === $currentLocale) aria-current="true" @endif>
                    <svg class="fpb-ls__check {{ $code === $currentLocale ? '' : 'fpb-ls__check--hidden' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0L4.296 10.71a1 1 0 011.42-1.42L8.5 12.07l6.79-6.78a1 1 0 011.414 0z"/>
                    </svg>
                    @if ($flags)
                        <span class="flag flag-{{ $fpbFlag[$code] ?? $code }}"></span>
                    @endif
                    <span class="fpb-ls__native">{{ $locale['native'] }}</span>
                    <span class="fpb-ls__code">{{ $code }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
