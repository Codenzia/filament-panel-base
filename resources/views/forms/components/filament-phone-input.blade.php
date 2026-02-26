@php
    $countries = $getCountries();
    $defaultCountryCode = $getDefaultCountryCode();
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $isReadOnly = $isReadOnly();
    $id = $getId();
    $placeholder = $getPlaceholder() ?? '501234567';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $statePath }}'){{ $isLive() ? '.live' : '' }},
            countryCode: @js($defaultCountryCode),
            number: '',
            open: false,
            search: '',
            countries: @js($countries),
            disabled: @js($isDisabled || $isReadOnly),

            init() {
                this.parseState();
                this.$watch('state', (val, oldVal) => {
                    {{-- Only re-parse if the change came from outside (e.g. Livewire/server) --}}
                    if (val !== (this.countryCode + this.number) && val !== oldVal) {
                        this.parseState();
                    }
                });
            },

            parseState() {
                if (!this.state) {
                    this.countryCode = this.countries[0]?.phone_code ?? @js($defaultCountryCode);
                    this.number = '';
                    return;
                }
                const sorted = [...this.countries].sort((a, b) => b.phone_code.length - a.phone_code.length);
                for (const c of sorted) {
                    if (this.state.startsWith(c.phone_code)) {
                        this.countryCode = c.phone_code;
                        this.number = this.state.substring(c.phone_code.length);
                        return;
                    }
                }
                this.number = this.state;
            },

            updateState() {
                this.state = this.number ? (this.countryCode + this.number) : null;
            },

            selectCountry(country) {
                this.countryCode = country.phone_code;
                this.open = false;
                this.search = '';
                this.updateState();
            },

            get selectedCountry() {
                return this.countries.find(c => c.phone_code === this.countryCode) || this.countries[0] || null;
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
        }"
        x-on:click.outside="open = false; search = ''"
        class="flex items-stretch"
    >
        {{-- Input wrapper — uses Filament's own fi-input-wrp for full theme compatibility --}}
        <div @class([
            'fi-input-wrp',
            'fi-disabled' => $isDisabled || $isReadOnly,
        ])>
            {{-- Country code prefix — uses Filament's non-inline prefix structure for the vertical divider --}}
            <div class="fi-input-wrp-prefix fi-input-wrp-prefix-has-content">
                <button
                    type="button"
                    x-on:click="if (!disabled) open = !open"
                    :disabled="disabled"
                    class="flex items-center gap-1.5 outline-none"
                    :class="{ 'cursor-not-allowed': disabled }"
                >
                    <template x-if="selectedCountry">
                        <span class="flag shrink-0" :class="'flag-' + selectedCountry.code"></span>
                    </template>
                    <span x-text="countryCode" class="fi-input-wrp-label whitespace-nowrap"></span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Phone number input — uses Filament's fi-input class --}}
            <div class="fi-input-wrp-content-ctn">
                <input
                    type="tel"
                    inputmode="numeric"
                    x-model="number"
                    x-on:input="number = number.replace(/[^0-9]/g, ''); updateState()"
                    x-on:blur="updateState()"
                    id="{{ $id }}"
                    placeholder="{{ $placeholder }}"
                    :disabled="disabled"
                    :readonly="disabled"
                    class="fi-input"
                />
            </div>
        </div>

        {{-- Country dropdown panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-10 w-64 rounded-md bg-white dark:bg-gray-700 shadow-lg ring-1 ring-black/5 dark:ring-white/10"
            x-cloak
        >
            {{-- Search --}}
            <div class="border-b border-gray-200 dark:border-white/10 p-2">
                <input
                    type="text"
                    x-model="search"
                    x-ref="countrySearch"
                    placeholder="{{ __('Search...') }}"
                    class="w-full rounded-md border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-600 px-2.5 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-primary-500"
                />
            </div>
            <div class="max-h-48 overflow-y-auto py-1">
                <template x-for="country in filteredCountries" :key="country.phone_code">
                    <button
                        type="button"
                        x-on:click="selectCountry(country)"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600"
                        :class="{ 'bg-primary-50 dark:bg-primary-900/20': countryCode === country.phone_code }"
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
</x-dynamic-component>
