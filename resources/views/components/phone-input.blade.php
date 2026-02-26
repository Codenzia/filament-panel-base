@props([
    'countries',
    'countryCodeModel' => 'country_code',
    'phoneModel' => 'whatsapp',
    'default' => null,
    'placeholder' => '501234567',
])

<div
    x-data="{
        open: false,
        search: '',
        selected: @entangle($countryCodeModel),
        countries: @js($countries->map(fn ($c) => [
            'code' => strtolower($c->code),
            'phone_code' => $c->phone_code,
            'name' => $c->name,
        ])),

        get selectedCountry() {
            return this.countries.find(c => c.phone_code === this.selected)
                || this.countries[0]
                || { code: '', phone_code: '{{ $default }}', name: '' };
        },

        get filteredCountries() {
            if (!this.search) return this.countries;
            const s = this.search.toLowerCase();
            return this.countries.filter(c =>
                c.name.toLowerCase().includes(s) ||
                c.phone_code.includes(s) ||
                c.code.includes(s)
            );
        },

        select(country) {
            this.selected = country.phone_code;
            this.open = false;
            this.search = '';
        },
    }"
    x-on:click.outside="open = false; search = ''"
    class="relative"
>
    {{-- Unified input group: country prefix + divider + phone number --}}
    <div class="flex items-stretch rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 shadow-sm focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
        {{-- Country code prefix --}}
        <button
            type="button"
            x-on:click="open = !open"
            class="flex items-center gap-1.5 shrink-0 px-2.5 text-sm text-gray-900 dark:text-white outline-none"
        >
            <span x-show="selectedCountry.code" class="flag shrink-0" :class="'flag-' + selectedCountry.code"></span>
            <span x-text="selectedCountry.phone_code" class="whitespace-nowrap"></span>
            <svg class="w-4 h-4 shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        {{-- Vertical divider --}}
        <div class="w-px self-stretch bg-gray-300 dark:bg-gray-600"></div>

        {{-- Phone number input --}}
        <input
            type="tel"
            wire:model="{{ $phoneModel }}"
            inputmode="numeric"
            x-on:input="$el.value = $el.value.replace(/[^0-9]/g, '')"
            class="flex-1 min-w-0 border-0 bg-transparent text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 text-sm focus:ring-0 focus:outline-none py-2 px-3"
            placeholder="{{ $placeholder }}"
        />
    </div>

    {{-- Country dropdown --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 mt-1 w-64 rounded-md bg-white dark:bg-gray-700 shadow-lg ring-1 ring-black/5 dark:ring-white/10"
        x-cloak
    >
        {{-- Search --}}
        <div class="border-b border-gray-200 dark:border-white/10 p-2">
            <input
                type="text"
                x-model="search"
                placeholder="{{ __('Search...') }}"
                class="w-full rounded-md border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-600 px-2.5 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-primary-500"
            />
        </div>
        <div class="max-h-48 overflow-y-auto py-1">
            <template x-for="country in filteredCountries" :key="country.phone_code">
                <button
                    type="button"
                    x-on:click="select(country)"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                    :class="{ 'bg-primary-50 dark:bg-primary-900/20': selected === country.phone_code }"
                >
                    <span class="flag shrink-0" :class="'flag-' + country.code"></span>
                    <span x-text="country.phone_code" class="font-mono"></span>
                    <span x-text="country.name" class="truncate text-gray-400"></span>
                </button>
            </template>
            <div x-show="filteredCountries.length === 0" class="px-3 py-2 text-sm text-gray-400">
                {{ __('No results') }}
            </div>
        </div>
    </div>
</div>
