<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \Codenzia\FilamentPanelBase\Middleware\SetLocale::isRtlLocale(app()->getLocale()) ? 'rtl' : 'ltr' }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }} — {{ __('Demo') }}</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        bg: {
                            page: '#0b1220',
                            paper: '#0f172a',
                        },
                        modal: '#111827',
                        primary: {
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        },
                    },
                },
            },
        };
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { background: #0b1220; color: #e5e7eb; }
    </style>
    @livewireStyles
</head>
<body class="min-h-screen antialiased">
    {{ $slot ?? '' }}
    @livewireScripts
</body>
</html>
