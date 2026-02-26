@props(['countries', 'wireModel' => 'country_code', 'default' => null])

<div x-data="{
    open: false,
    selected: @entangle($wireModel),
    countries: @js($countries->map(fn ($c) => ['code' => strtolower($c->code), 'phone_code' => $c->phone_code, 'label' => strtoupper($c->code)])),
    get selectedCountry() {
        return this.countries.find(c => c.phone_code === this.selected) || this.countries[0] || { code: '', phone_code: '{{ $default }}' };
    },
    select(country) {
        this.selected = country.phone_code;
        this.open = false;
    }
}" x-on:click.outside="open = false" class="relative w-32 shrink-0">
    {{-- Trigger button --}}
    <button type="button" x-on:click="open = !open"
        class="flex items-center gap-1.5 w-full h-9.5 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm px-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 focus:outline-none">
        <span x-show="selectedCountry.code" class="fi shrink-0" :class="'fi-' + selectedCountry.code"></span>
        <span x-text="selectedCountry.phone_code" class="truncate"></span>
        <svg class="w-4 h-4 ms-auto shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    {{-- Dropdown --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 mt-1 w-44 rounded-md bg-white dark:bg-gray-700 shadow-lg ring-1 ring-black/5 dark:ring-white/10 max-h-48 overflow-y-auto"
        x-cloak>
        <template x-for="country in countries" :key="country.phone_code">
            <button type="button" x-on:click="select(country)"
                class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                :class="{ 'bg-primary-50 dark:bg-primary-900/20': selected === country.phone_code }">
                <span class="fi shrink-0" :class="'fi-' + country.code"></span>
                <span x-text="country.phone_code"></span>
                <span x-text="country.label" class="text-xs text-gray-400"></span>
            </button>
        </template>
    </div>
</div>
