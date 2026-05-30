<div class="flex max-h-[70vh] flex-col" x-data="{ activeIndex: 0 }" x-init="$watch('$wire.query', () => activeIndex = 0)">
    <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
        <input
            type="text"
            wire:model.live.debounce.150ms="query"
            x-ref="searchInput"
            autofocus
            placeholder="{{ __('filament-panel-base::command-palette.placeholder') }}"
            class="block w-full border-0 bg-transparent p-0 text-base text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100 dark:placeholder-gray-500" />
    </div>

    <div class="flex-1 overflow-y-auto p-2"
        @keydown.window.escape="$dispatch('cmd-palette-close')"
        @keydown.window.arrow-down.prevent="activeIndex = Math.min(activeIndex + 1, $el.querySelectorAll('[data-cmd-item]').length - 1); $el.querySelectorAll('[data-cmd-item]')[activeIndex]?.scrollIntoView({ block: 'nearest' })"
        @keydown.window.arrow-up.prevent="activeIndex = Math.max(activeIndex - 1, 0); $el.querySelectorAll('[data-cmd-item]')[activeIndex]?.scrollIntoView({ block: 'nearest' })"
        @keydown.window.enter.prevent="$el.querySelectorAll('[data-cmd-item]')[activeIndex]?.click()">

        @php $flatIndex = 0; @endphp

        @forelse ($groups as $groupName => $items)
            <div class="mt-2 first:mt-0">
                <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                    {{ $groupName }}
                </p>
                <ul>
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ $item->url }}"
                                data-cmd-item
                                data-cmd-index="{{ $flatIndex }}"
                                @click="$dispatch('cmd-palette-close')"
                                :class="activeIndex === {{ $flatIndex }} ? 'bg-primary-50 dark:bg-primary-900/30' : ''"
                                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">
                                @if ($item->icon)
                                    <x-filament::icon :icon="$item->icon" class="h-5 w-5 shrink-0 text-gray-500" />
                                @endif
                                <span class="flex-1 truncate">
                                    {{ $item->label }}
                                    @if ($item->description)
                                        <span class="ml-2 text-xs text-gray-400">{{ $item->description }}</span>
                                    @endif
                                </span>
                                @if ($item->shortcut)
                                    <kbd class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500 dark:bg-white/10 dark:text-gray-400">{{ $item->shortcut }}</kbd>
                                @endif
                            </a>
                        </li>
                        @php $flatIndex++; @endphp
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('filament-panel-base::command-palette.no_results') }}
            </p>
        @endforelse
    </div>
</div>
