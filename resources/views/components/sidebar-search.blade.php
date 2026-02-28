{{--
    Injected at SIDEBAR_NAV_START — search input that filters sidebar navigation items.
    Uses Alpine.js to match item labels client-side (no server round-trip).
    Hidden when sidebar is collapsed to icon-only mode.
--}}
<div
    x-data="{
        search: '',
        filter() {
            const query = this.search.toLowerCase().trim()
            const nav = this.$el.closest('.fi-sidebar-nav')
            if (! nav) return

            const groups = nav.querySelectorAll('.fi-sidebar-nav-groups > .fi-sidebar-group')

            groups.forEach(group => {
                const items = group.querySelectorAll('.fi-sidebar-item')
                let visibleCount = 0

                items.forEach(item => {
                    const label = item.querySelector('.fi-sidebar-item-label')
                    if (! label) return

                    const text = label.textContent.toLowerCase()
                    const matches = ! query || text.includes(query)

                    item.style.display = matches ? '' : 'none'
                    if (matches) visibleCount++
                })

                group.style.display = (! query || visibleCount > 0) ? '' : 'none'
            })
        },
        clear() {
            this.search = ''
            this.filter()
        }
    }"
    x-show="$store.sidebar.isOpen"
    x-transition:enter="fi-transition-enter"
    x-transition:enter-start="fi-transition-enter-start"
    x-transition:enter-end="fi-transition-enter-end"
    class="-mx-2 pb-2"
>
    <div class="relative">
        {{-- Search icon --}}
        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                class="h-4 w-4 text-gray-400 dark:text-gray-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </div>

        <input
            x-model="search"
            x-on:input.debounce.150ms="filter()"
            type="text"
            placeholder="{{ __('Search menu...') }}"
            class="w-full rounded-lg border-0 bg-gray-100 py-1.5 ps-8 pe-8 text-sm text-gray-700 placeholder-gray-400 ring-1 ring-gray-200 transition focus:bg-white focus:ring-primary-500 dark:bg-white/5 dark:text-gray-200 dark:placeholder-gray-500 dark:ring-white/10 dark:focus:bg-white/10 dark:focus:ring-primary-500"
        />

        {{-- Clear button --}}
        <button
            x-show="search.length > 0"
            x-on:click="clear()"
            type="button"
            class="absolute inset-y-0 end-0 flex items-center pe-2.5"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                class="h-4 w-4 text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
