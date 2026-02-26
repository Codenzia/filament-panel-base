{{-- Dark Mode Script: Prevents flash of unstyled content (FOUC) on page load.
     Must be placed in <head> BEFORE any stylesheets so the 'dark' class
     is applied before the first paint.

     Usage: <x-panel-base::dark-mode-script />

     Reads localStorage('theme'). Falls back to prefers-color-scheme.
     Works with the <x-panel-base::dark-mode-toggle> component. --}}

<script>
    if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
</script>
