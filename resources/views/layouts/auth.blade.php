<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @livewireStyles
</head>
<body class="min-h-screen bg-surface-page dark:bg-surface-page-dark antialiased">
    <main class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ config('app.name') }}</h1>
            </div>

            {{ $slot ?? '' }}
        </div>
    </main>
    @livewireScripts
</body>
</html>
