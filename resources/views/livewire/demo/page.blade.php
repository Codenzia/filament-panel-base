<div class="min-h-screen bg-bg-page text-gray-200">
    @if (! $unlocked)
        {{-- Password gate --}}
        <div class="min-h-screen flex items-center justify-center p-4">
            <form wire:submit.prevent="unlock"
                  class="w-full max-w-md bg-modal rounded-2xl shadow-xl p-8 border border-gray-800">
                <h1 class="text-2xl font-bold text-white mb-1">{{ config('app.name') }} — {{ __('Demo') }}</h1>
                <p class="text-sm text-gray-400 mb-6">{{ __('Enter the demo password to continue.') }}</p>

                <label class="block text-sm font-medium text-gray-300 mb-2">{{ __('Password') }}</label>
                <input type="password" wire:model.defer="gatePassword" autofocus required
                       class="w-full px-4 py-2.5 rounded-lg bg-gray-900 border border-gray-700 text-white focus:outline-none focus:border-primary-500"
                       placeholder="••••••••">

                @if ($gateError !== '')
                    <p class="mt-2 text-sm text-red-400">{{ $gateError }}</p>
                @endif

                <button type="submit"
                        class="mt-6 w-full px-4 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-medium transition">
                    {{ __('Unlock Demo') }}
                </button>
            </form>
        </div>
    @else
        {{-- Header --}}
        <div class="bg-modal border-b border-gray-800">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 class="text-2xl font-bold text-white">
                            {{ config('app.name') }} — {{ __('Demo') }}
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">
                            {{ __('Click "Login" to switch to any user. Password for all accounts:') }}
                            <code class="bg-gray-800 px-2 py-0.5 rounded text-xs font-mono">{{ $sharedPassword }}</code>
                        </p>
                    </div>
                    <div class="flex items-center gap-3 flex-wrap">
                        @include('filament-panel-base::components.locale-switcher', [
                            'locales' => \Codenzia\FilamentPanelBase\Middleware\SetLocale::getLocales(),
                            'currentLocale' => app()->getLocale(),
                        ])
                        @if ($hasStandardSeeder)
                            <button wire:click="promptSeeder('standard')" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50 transition">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75"/>
                                </svg>
                                {{ __('Seed Standard Data') }}
                            </button>
                        @endif
                        @if ($hasDemoSeeder)
                            <button wire:click="promptSeeder('demo')" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white disabled:opacity-50 transition">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182"/>
                                </svg>
                                {{ __('Seed Demo Data') }}
                            </button>
                        @endif
                        @auth
                            <span class="text-sm text-gray-400">
                                {{ __('Logged in as') }}:
                                <strong class="text-white">{{ Auth::user()->name ?? Auth::user()->email }}</strong>
                            </span>
                            <a href="{{ $appUrl }}"
                               class="px-4 py-2 text-sm font-medium rounded-lg bg-green-600 hover:bg-green-700 text-white transition">
                                {{ __('Go to App') }} →
                            </a>
                        @endauth
                        <button wire:click="lock" title="{{ __('Lock demo page') }}"
                                class="px-3 py-2 text-sm rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-300 transition">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div wire:loading.flex wire:target="confirmSeeder"
             class="fixed inset-0 z-50 items-center justify-center bg-black/70 backdrop-blur-sm">
            <div class="bg-modal rounded-2xl p-8 max-w-md text-center border border-gray-800">
                <svg class="animate-spin w-10 h-10 mx-auto text-primary-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <p class="text-white font-medium">{{ __('Resetting database and reseeding...') }}</p>
                <p class="text-sm text-gray-400 mt-1">{{ __('This may take a few seconds.') }}</p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 py-6 space-y-6">
            @php
                $demoSections = (array) config('filament-panel-base.demo.sections', []);
            @endphp

            {{-- Section slot: before_stats --}}
            @if (! empty($demoSections['before_stats']))
                @livewire($demoSections['before_stats'])
            @endif

            {{-- Stats grid --}}
            @if (! empty($stats))
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    @foreach ($stats as $stat)
                        <div class="bg-modal rounded-xl p-4 flex items-center gap-3 border border-gray-800">
                            <div class="text-gray-400 p-2 rounded-full bg-emerald-500/10">
                                @if (str_starts_with($stat['icon'], 'heroicon-'))
                                    <x-dynamic-component :component="$stat['icon']" class="w-5 h-5 text-primary-400" />
                                @else
                                    <span class="w-5 h-5 block"></span>
                                @endif
                            </div>
                            <div>
                                <p class="text-xl font-bold text-white">{{ number_format((int) $stat['value']) }}</p>
                                <p class="text-xs text-gray-400">{{ $stat['label'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Section slot: after_stats --}}
            @if (! empty($demoSections['after_stats']))
                @livewire($demoSections['after_stats'])
            @endif

            {{-- Section slot: before_users --}}
            @if (! empty($demoSections['before_users']))
                @livewire($demoSections['before_users'])
            @endif

            {{-- Users --}}
            @if (! empty($users))
                <div class="bg-modal rounded-xl overflow-hidden border border-gray-800">
                    <div class="px-6 py-4 border-b border-gray-800">
                        <h2 class="text-base font-semibold text-white">{{ __('Demo Users') }}</h2>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ count($users) }} {{ __('users') }}
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-800/50 text-left">
                                    <th class="px-4 py-3 font-medium text-gray-400">{{ __('User') }}</th>
                                    <th class="px-4 py-3 font-medium text-gray-400">{{ __('Roles') }}</th>
                                    <th class="px-4 py-3 font-medium text-gray-400 text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr class="border-t border-gray-800 hover:bg-gray-800/40 transition {{ $user['is_current'] ? 'bg-primary-500/5' : '' }}">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                @if ($user['avatar'])
                                                    <img src="{{ $user['avatar'] }}" alt="" class="w-9 h-9 rounded-full object-cover">
                                                @else
                                                    <div class="w-9 h-9 rounded-full bg-gray-700 flex items-center justify-center text-xs font-medium text-gray-300">
                                                        {{ strtoupper(substr($user['name'], 0, 2)) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <p class="font-medium text-white">
                                                        {{ $user['name'] }}
                                                        @if ($user['is_current'])
                                                            <span class="text-xs text-green-400 ml-1">({{ __('You') }})</span>
                                                        @endif
                                                    </p>
                                                    <p class="text-xs text-gray-400">{{ $user['email'] }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse ($user['roles'] as $role)
                                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded border
                                                        @if ($role === 'super_admin') bg-red-500/10 text-red-400 border-red-500/20
                                                        @elseif ($role === 'admin') bg-amber-500/10 text-amber-400 border-amber-500/20
                                                        @else bg-gray-500/10 text-gray-300 border-gray-500/20
                                                        @endif">
                                                        {{ __(ucfirst(str_replace('_', ' ', $role))) }}
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-gray-500">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if ($user['is_current'])
                                                <a href="{{ $appUrl }}"
                                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 hover:bg-green-700 text-white transition">
                                                    {{ __('Go to App') }}
                                                </a>
                                            @elseif ($user['is_super'])
                                                <span class="text-xs text-gray-500">—</span>
                                            @else
                                                <button wire:click="loginAs('{{ $user['id'] }}')"
                                                        wire:loading.attr="disabled"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-700 hover:bg-gray-600 text-white transition">
                                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                                                    </svg>
                                                    {{ __('Login') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Section slot: after_users --}}
            @if (! empty($demoSections['after_users']))
                @livewire($demoSections['after_users'])
            @endif
        </div>

        {{-- Footer --}}
        <footer class="border-t border-gray-800 mt-8">
            <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between flex-wrap gap-2 text-xs text-gray-500">
                <div>
                    {{ __('Built') }}: <span class="text-gray-300 font-mono">{{ $footer['built_at'] }}</span>
                </div>
                <div class="flex items-center gap-4">
                    <span>PHP <span class="text-gray-300 font-mono">{{ $footer['php'] }}</span></span>
                    <span>Laravel <span class="text-gray-300 font-mono">{{ $footer['laravel'] }}</span></span>
                    <span>Filament <span class="text-gray-300 font-mono">{{ $footer['filament'] }}</span></span>
                </div>
            </div>
        </footer>

        {{-- Seed Password Modal --}}
        @if ($showPasswordModal)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
                 wire:click.self="cancelSeeder">
                <form wire:submit.prevent="confirmSeeder"
                      class="bg-modal rounded-2xl border border-gray-800 shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-white">{{ __('Enter Seeder Password') }}</h3>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ __('This will wipe ALL data and re-seed. Enter the password to continue.') }}
                    </p>

                    <div class="mt-4">
                        <input type="password" wire:model.defer="seederPassword" autofocus required
                               class="w-full px-4 py-2.5 rounded-lg bg-gray-900 border border-gray-700 text-white focus:outline-none focus:border-primary-500"
                               placeholder="{{ __('Password') }}">
                        @if ($passwordError !== '')
                            <p class="mt-2 text-sm text-red-400">{{ $passwordError }}</p>
                        @endif
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="cancelSeeder"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-200 transition">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white transition">
                            {{ __('Confirm & Seed') }}
                        </button>
                    </div>
                </form>
            </div>
        @endif
    @endif
</div>
