<x-filament-panels::page>
    <div class="space-y-6">
        <div class="max-w-md">
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    :placeholder="fpb_trans('Search notifications…')"
                />
            </x-filament::input.wrapper>
        </div>

        @php($groups = $this->getGroupedTriggers())

        @if (empty($groups))
            <x-filament::section>
                <div class="flex flex-col items-center gap-2 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-bell-slash" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                    @if ($search !== '')
                        {{ fpb_trans('No notifications match ":search".', ['search' => $search]) }}
                    @else
                        {{ fpb_trans('No notification triggers are registered yet.') }}
                    @endif
                </div>
            </x-filament::section>
        @endif

        @foreach ($groups as $group => $triggers)
            <x-filament::section>
                <x-slot name="heading">
                    {{ $group }}
                </x-slot>

                <div class="-mx-6 overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-start text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="px-6 py-2 text-start font-medium">{{ fpb_trans('Notification') }}</th>
                                @foreach ($this->getChannels() as $channel)
                                    <th class="px-6 py-2 text-center font-medium">{{ $this->channelLabel($channel) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($triggers as $key => $meta)
                                <tr wire:key="trigger-{{ $key }}">
                                    <td class="px-6 py-3 align-middle">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $meta['label'] }}</span>
                                    </td>
                                    @foreach ($this->getChannels() as $channel)
                                        <td class="px-6 py-3 text-center align-middle">
                                            @if (in_array($channel, $meta['channels'], true))
                                                <button
                                                    type="button"
                                                    wire:click="toggleChannel('{{ $key }}', '{{ $channel }}')"
                                                    role="switch"
                                                    aria-checked="{{ $this->isChannelEnabled($key, $channel) ? 'true' : 'false' }}"
                                                    aria-label="{{ fpb_trans(':channel notifications for :trigger', ['channel' => $this->channelLabel($channel), 'trigger' => $meta['label']]) }}"
                                                    @class([
                                                        'inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900',
                                                        'bg-primary-600' => $this->isChannelEnabled($key, $channel),
                                                        'bg-gray-200 dark:bg-gray-700' => ! $this->isChannelEnabled($key, $channel),
                                                    ])
                                                >
                                                    <span
                                                        @class([
                                                            'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform',
                                                            'translate-x-5 rtl:-translate-x-5' => $this->isChannelEnabled($key, $channel),
                                                            'translate-x-1 rtl:-translate-x-1' => ! $this->isChannelEnabled($key, $channel),
                                                        ])
                                                    ></span>
                                                </button>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">&mdash;</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
