<x-filament-panels::page>
    @php
        $password = $this->currentPassword();
        $source = $this->passwordSource();
        $meta = $this->metadata();
        $reveal = $this->reveal;
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Current Password') }}
            </x-slot>
            <x-slot name="description">
                {{ __('Used to unlock the /demo page and gate the seeder buttons.') }}
            </x-slot>

            @if ($password === null)
                <div class="flex items-center gap-3 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-900/30 dark:text-warning-200">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                    <span>
                        {{ __('No password is set. The /demo page will auto-unlock for everyone until you generate one.') }}
                    </span>
                </div>
            @else
                <div class="flex flex-wrap items-center gap-3">
                    <code
                        class="rounded-lg bg-gray-100 px-4 py-2 font-mono text-sm tracking-wider dark:bg-gray-800"
                        style="min-width: 220px;"
                    >
                        @if ($reveal)
                            {{ $password }}
                        @else
                            {{ str_repeat('•', max(strlen($password), 12)) }}
                        @endif
                    </code>
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        @if ($source === 'database')
                            <x-filament::icon icon="heroicon-o-circle-stack" class="h-3.5 w-3.5" />
                            {{ __('Stored in database') }}
                        @elseif ($source === 'env')
                            <x-filament::icon icon="heroicon-o-document-text" class="h-3.5 w-3.5" />
                            {{ __('From .env') }}
                        @endif
                    </span>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                {{ __('Share') }}
            </x-slot>
            <x-slot name="description">
                {{ __('Copy the public link to share with prospects.') }}
            </x-slot>

            <div class="space-y-2 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 min-w-[80px]">{{ __('URL') }}:</span>
                    <a
                        href="{{ $this->demoUrl() }}"
                        target="_blank"
                        rel="noopener"
                        class="font-mono text-primary-600 hover:underline dark:text-primary-400"
                    >
                        {{ $this->demoUrl() }}
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500 dark:text-gray-400 min-w-[80px]">{{ __('Password') }}:</span>
                    <code class="font-mono text-gray-700 dark:text-gray-300">
                        @if ($password === null)
                            <em class="text-gray-400">{{ __('not set') }}</em>
                        @elseif ($reveal)
                            {{ $password }}
                        @else
                            {{ str_repeat('•', max(strlen($password), 12)) }}
                        @endif
                    </code>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                {{ __('Activity') }}
            </x-slot>

            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Last rotated') }}</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                        @if ($meta['rotated_at'])
                            {{ $meta['rotated_at']->diffForHumans() }}
                            <span class="text-xs text-gray-400">({{ $meta['rotated_at']->toDateTimeString() }})</span>
                        @else
                            <span class="text-gray-400">{{ __('Never') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Last successful unlock') }}</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">
                        @if ($meta['last_used_at'])
                            {{ $meta['last_used_at']->diffForHumans() }}
                            <span class="text-xs text-gray-400">({{ $meta['last_used_at']->toDateTimeString() }})</span>
                        @else
                            <span class="text-gray-400">{{ __('Never') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
