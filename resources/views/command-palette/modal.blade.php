<div
    x-data="{
        open: false,
        toggle() { this.open = ! this.open; if (this.open) { this.$nextTick(() => this.$refs.body?.querySelector('input')?.focus()) } },
        close() { this.open = false; }
    }"
    @keydown.window.cmd.k.prevent="toggle()"
    @keydown.window.ctrl.k.prevent="toggle()"
    @cmd-palette-close.window="close()"
    class="fpb-command-palette">

    <template x-if="open">
        <div
            x-cloak
            x-on:click.self="close()"
            class="fixed inset-0 z-[60] flex items-start justify-center bg-black/40 p-4 pt-[10vh] backdrop-blur-sm">
            <div
                x-ref="body"
                x-on:click.stop
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-900 dark:ring-gray-700">
                @livewire('filament-panel-base::command-palette')
            </div>
        </div>
    </template>
</div>
