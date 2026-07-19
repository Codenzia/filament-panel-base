<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    {{--
        Minimal, self-contained styling so this fallback layout is presentable
        even when the host has not compiled the package's Tailwind theme. Hosts
        that supply their own auth layout (config filament-panel-base.auth.layout)
        never load this. Kept intentionally small and dark-mode aware.
    --}}
    <style>
        :root {
            color-scheme: light dark;
            --fpb-page: #f3f4f6;
            --fpb-card: #ffffff;
            --fpb-text: #111827;
            --fpb-muted: #6b7280;
            --fpb-border: #d1d5db;
            --fpb-input: #ffffff;
            --fpb-primary: #4f46e5;
            --fpb-primary-text: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --fpb-page: #0f172a;
                --fpb-card: #1e293b;
                --fpb-text: #f1f5f9;
                --fpb-muted: #94a3b8;
                --fpb-border: #334155;
                --fpb-input: #0f172a;
                --fpb-primary: #6366f1;
                --fpb-primary-text: #ffffff;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: var(--fpb-page);
            color: var(--fpb-text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
        }
        .fpb-auth-shell { width: 100%; max-width: 26rem; }
        .fpb-auth-brand {
            margin: 0 0 1.5rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .fpb-auth-card {
            background: var(--fpb-card);
            border: 1px solid var(--fpb-border);
            border-radius: 0.75rem;
            padding: 1.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        }
        .fpb-auth-card label { display: block; margin-bottom: 0.35rem; font-size: 0.875rem; font-weight: 500; }
        .fpb-auth-card input:not([type="checkbox"]):not([type="radio"]),
        .fpb-auth-card select {
            width: 100%;
            padding: 0.55rem 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid var(--fpb-border);
            border-radius: 0.5rem;
            background: var(--fpb-input);
            color: var(--fpb-text);
            font-size: 0.95rem;
        }
        .fpb-auth-card input:focus, .fpb-auth-card select:focus {
            outline: 2px solid var(--fpb-primary);
            outline-offset: 1px;
            border-color: var(--fpb-primary);
        }
        .fpb-auth-card button, .fpb-auth-card [type="submit"] {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.6rem 1rem;
            border: 0;
            border-radius: 0.5rem;
            background: var(--fpb-primary);
            color: var(--fpb-primary-text);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }
        .fpb-auth-card button:hover, .fpb-auth-card [type="submit"]:hover { filter: brightness(1.05); }
        .fpb-auth-card a { color: var(--fpb-primary); }
    </style>

    @livewireStyles
</head>
<body class="min-h-screen bg-surface-page dark:bg-surface-page-dark antialiased">
    <main class="fpb-auth-shell flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="fpb-auth-brand mb-6 text-center">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ config('app.name') }}</h1>
            </div>

            <div class="fpb-auth-card">
                {{ $slot ?? '' }}
            </div>
        </div>
    </main>
    @livewireScripts
</body>
</html>
