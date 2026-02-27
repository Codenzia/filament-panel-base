{{--
    Injected at SIDEBAR_NAV_START — top of the sidebar nav, above all nav items.
    Positioned absolutely to stick out from the right edge of the sidebar.
    Alpine directives work via the parent <aside x-data="{}"> scope.
    Adjust top-* to move the button up or down relative to the sidebar top edge.
--}}
<div class="absolute z-50"
    :class="{
        '-right-1 top-2': $store.sidebar.isOpen,
        'left-0 right-2 flex justify-center top-2': !$store.sidebar.isOpen
    }">

    <button x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()" type="button"
        class="flex items-center justify-center rounded-full bg-white shadow-md ring-1 ring-gray-200 transition hover:bg-gray-50 dark:bg-gray-800 dark:ring-gray-700 dark:hover:bg-gray-700"
        :class="{
            'mx-8 h-9 w-9': $store.sidebar.isOpen,
            'h-5 w-5': !$store.sidebar.isOpen
        }"
        :title="$store.sidebar.isOpen ?
            '{{ __('filament-panels::layout.actions.sidebar.collapse.label') }}' :
            '{{ __('filament-panels::layout.actions.sidebar.expand.label') }}'">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
            class="transition-transform text-gray-500 dark:text-gray-400"
            :class="{
                'rotate-180': !$store.sidebar.isOpen,
                'h-7 w-7': $store.sidebar.isOpen,
                'h-4 w-4': !$store.sidebar.isOpen
            }">
            <path fill-rule="evenodd"
                d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                clip-rule="evenodd" />
        </svg>
    </button>
</div>
