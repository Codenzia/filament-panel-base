{{-- "Visit Website" button — injected via GLOBAL_SEARCH_BEFORE render hook.
     Props: $label (string) --}}

@props([
    'label' => __('Visit Website'),
])

<a href="/"
   target="_blank"
   class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/10"
   title="{{ $label }}">
    @svg('heroicon-o-arrow-top-right-on-square', 'h-5 w-5')
    <span class="hidden sm:inline">{{ $label }}</span>
</a>
