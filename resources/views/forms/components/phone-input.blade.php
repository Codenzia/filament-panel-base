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
        {{-- Scoped dropdown styles using CSS custom properties for dark mode --}}
        <style>
            [data-fi-phone] {
                --pi-panel-bg: #ffffff;
                --pi-panel-border: rgba(0, 0, 0, 0.05);
                --pi-panel-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
                --pi-search-bg: #f9fafb;
                --pi-search-color: #111827;
                --pi-search-border: #e5e7eb;
                --pi-item-color: #374151;
                --pi-item-hover-bg: #f3f4f6;
                --pi-item-selected-bg: #eff6ff;
                --pi-muted-color: #6b7280;
                --pi-divider: #e5e7eb;
                --pi-no-results: #9ca3af;
            }
            :is(.dark) [data-fi-phone],
            :is(.dark) [data-fi-phone] * {
                --pi-panel-bg: #1e293b;
                --pi-panel-border: rgba(255, 255, 255, 0.1);
                --pi-panel-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.4), 0 4px 6px -4px rgb(0 0 0 / 0.3);
                --pi-search-bg: #334155;
                --pi-search-color: #f1f5f9;
                --pi-search-border: rgba(255, 255, 255, 0.1);
                --pi-item-color: #e2e8f0;
                --pi-item-hover-bg: #334155;
                --pi-item-selected-bg: #1e3a5f;
                --pi-muted-color: #94a3b8;
                --pi-divider: rgba(255, 255, 255, 0.1);
                --pi-no-results: #94a3b8;
            }
        </style>

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
            data-fi-phone
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-10 w-64 rounded-lg"
            style="background: var(--pi-panel-bg); border: 1px solid var(--pi-panel-border); box-shadow: var(--pi-panel-shadow);"
            x-cloak
        >
            {{-- Search --}}
            <div class="border-b p-2" style="border-color: var(--pi-divider);">
                <input
                    type="text"
                    x-model="search"
                    x-ref="countrySearch"
                    placeholder="{{ __('Search...') }}"
                    class="w-full rounded-md px-2.5 py-1.5 text-sm focus:border-primary-500 focus:ring-primary-500"
                    style="background: var(--pi-search-bg); color: var(--pi-search-color); border: 1px solid var(--pi-search-border);"
                />
            </div>
            <div class="max-h-48 overflow-y-auto py-1">
                <template x-for="country in filteredCountries" :key="country.phone_code">
                    <button
                        type="button"
                        x-on:click="selectCountry(country)"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-sm"
                        style="color: var(--pi-item-color);"
                        x-bind:style="countryCode === country.phone_code
                            ? 'color: var(--pi-item-color); background: var(--pi-item-selected-bg);'
                            : 'color: var(--pi-item-color);'"
                        x-on:mouseenter="$el.style.background = 'var(--pi-item-hover-bg)'"
                        x-on:mouseleave="$el.style.background = countryCode === country.phone_code ? 'var(--pi-item-selected-bg)' : 'transparent'"
                    >
                        <span class="flag shrink-0" :class="'flag-' + country.code"></span>
                        <span x-text="country.phone_code" class="font-mono"></span>
                        <span x-text="country.name" class="truncate" style="color: var(--pi-muted-color);"></span>
                    </button>
                </template>
                <div x-show="filteredCountries.length === 0" class="px-3 py-2 text-sm" style="color: var(--pi-no-results);">
                    {{ __('No results') }}
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
