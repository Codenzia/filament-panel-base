{{-- Inline brand SVG for a Socialite provider button.
     Renders a 20x20 mark for the common providers; falls back to a generic
     lock icon for unknown names. Defined inline so the plugin ships zero
     external icon dependencies.
     Props:
       $provider — provider key (e.g. 'google', 'github').
       $class    — tailwind classes for sizing/spacing. --}}

@props([
    'provider' => '',
    'class' => 'h-5 w-5',
])

@php
    $key = strtolower($provider);
@endphp

@switch($key)
    @case('google')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/>
        </svg>
        @break

    @case('github')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.57.11.78-.25.78-.55v-1.93c-3.2.7-3.87-1.54-3.87-1.54-.52-1.32-1.27-1.67-1.27-1.67-1.04-.71.08-.7.08-.7 1.15.08 1.76 1.18 1.76 1.18 1.02 1.75 2.69 1.25 3.34.96.1-.74.4-1.25.72-1.54-2.55-.29-5.23-1.28-5.23-5.69 0-1.26.45-2.29 1.18-3.1-.12-.29-.51-1.46.11-3.04 0 0 .97-.31 3.17 1.18A11 11 0 0 1 12 6.8c.98 0 1.97.13 2.9.39 2.2-1.49 3.17-1.18 3.17-1.18.63 1.58.24 2.75.12 3.04.74.81 1.18 1.84 1.18 3.1 0 4.42-2.69 5.4-5.25 5.68.41.36.78 1.06.78 2.13v3.16c0 .31.21.67.79.55 4.56-1.53 7.85-5.83 7.85-10.91C23.5 5.65 18.35.5 12 .5z"/>
        </svg>
        @break

    @case('facebook')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#1877F2" d="M24 12c0-6.63-5.37-12-12-12S0 5.37 0 12c0 5.99 4.39 10.95 10.13 11.85V15.47H7.08V12h3.05V9.36c0-3.01 1.79-4.67 4.53-4.67 1.31 0 2.69.23 2.69.23v2.96h-1.52c-1.5 0-1.96.93-1.96 1.88V12h3.34l-.53 3.47h-2.81v8.38C19.61 22.95 24 17.99 24 12z"/>
        </svg>
        @break

    @case('apple')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M17.5 12.6c0-2.9 2.4-4.3 2.5-4.4-1.4-2-3.5-2.3-4.3-2.3-1.8-.2-3.6 1.1-4.5 1.1-.9 0-2.4-1-4-1-2 0-3.9 1.2-4.9 3-2.1 3.6-.5 9 1.5 11.9 1 1.4 2.2 3 3.7 2.9 1.5-.1 2.1-1 3.9-1s2.3 1 3.9 1c1.6 0 2.6-1.5 3.6-2.9.7-1 1.4-2.1 1.8-3.4-1.7-.7-3.2-2.4-3.2-4.9zM14.7 4.1c.8-1 1.4-2.4 1.2-3.8-1.2 0-2.6.8-3.5 1.8-.8.9-1.5 2.3-1.3 3.6 1.3.1 2.7-.6 3.6-1.6z"/>
        </svg>
        @break

    @case('microsoft')
    @case('azure')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 23 23" aria-hidden="true">
            <path fill="#F25022" d="M1 1h10v10H1z"/>
            <path fill="#7FBA00" d="M12 1h10v10H12z"/>
            <path fill="#00A4EF" d="M1 12h10v10H1z"/>
            <path fill="#FFB900" d="M12 12h10v10H12z"/>
        </svg>
        @break

    @case('twitter')
    @case('x')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
        </svg>
        @break

    @case('linkedin')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#0A66C2" d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.95v5.66H9.36V9h3.41v1.56h.05c.47-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.56V9h3.56v11.45zM22.23 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.46c.98 0 1.77-.77 1.77-1.72V1.72C24 .77 23.21 0 22.23 0z"/>
        </svg>
        @break

    @case('gitlab')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#E24329" d="M23.6 9.6 23.5 9.4 20.8 2.4a.7.7 0 0 0-1.3 0L17 9.3H7l-2.5-6.9a.7.7 0 0 0-1.3 0L.5 9.4l-.1.2a4.8 4.8 0 0 0 1.6 5.5l8 5.8h.1l4 3 4-3 8-5.8a4.8 4.8 0 0 0 1.5-5.5z"/>
        </svg>
        @break

    @case('bitbucket')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#2684FF" d="M.77 2.04a.77.77 0 0 0-.77.9l3.26 19.94c.07.43.44.74.87.74h15.85c.34 0 .62-.24.68-.58l3.26-20.1a.77.77 0 0 0-.77-.9zm13.74 14.43h-5l-1.35-7.08h7.55z"/>
        </svg>
        @break

    @case('slack')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#E01E5A" d="M6.5 15.2a2.1 2.1 0 1 1-2.1-2.1h2.1zm1.1 0a2.1 2.1 0 1 1 4.2 0v5.3a2.1 2.1 0 1 1-4.2 0z"/>
            <path fill="#36C5F0" d="M9.7 6.5a2.1 2.1 0 1 1 2.1-2.1v2.1zm0 1.1a2.1 2.1 0 1 1 0 4.2H4.4a2.1 2.1 0 1 1 0-4.2z"/>
            <path fill="#2EB67D" d="M18.4 9.7a2.1 2.1 0 1 1 2.1 2.1h-2.1zm-1.1 0a2.1 2.1 0 1 1-4.2 0V4.4a2.1 2.1 0 1 1 4.2 0z"/>
            <path fill="#ECB22E" d="M15.2 18.4a2.1 2.1 0 1 1-2.1 2.1v-2.1zm0-1.1a2.1 2.1 0 1 1 0-4.2h5.3a2.1 2.1 0 1 1 0 4.2z"/>
        </svg>
        @break

    @case('discord')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#5865F2" d="M20.32 4.37A19.8 19.8 0 0 0 15.34 3l-.25.51c1.85.45 2.71 1.1 3.6 1.91A13.4 13.4 0 0 0 6.32 5.42c.88-.79 1.94-1.45 3.6-1.91L9.66 3a19.8 19.8 0 0 0-4.98 1.37C2.36 8.5 1.72 12.5 2.04 16.46c1.95 1.43 3.83 2.3 5.69 2.87.46-.63.87-1.3 1.22-2-1.34-.5-2.13-.97-2.7-1.43.07-.05.14-.1.21-.16 3.5 1.64 7.5 1.64 10.96 0 .07.06.14.11.22.16-.58.46-1.36.93-2.7 1.43.35.7.76 1.37 1.22 2 1.86-.57 3.74-1.44 5.69-2.87.38-4.62-.65-8.58-3.32-12.09zM9.34 14.46c-1.12 0-2.04-1.01-2.04-2.26s.9-2.27 2.04-2.27c1.13 0 2.05 1.01 2.04 2.27 0 1.25-.9 2.26-2.04 2.26zm5.32 0c-1.13 0-2.04-1.01-2.04-2.26s.9-2.27 2.04-2.27c1.13 0 2.05 1.01 2.04 2.27 0 1.25-.9 2.26-2.04 2.26z"/>
        </svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5zm-3 8V6a3 3 0 1 1 6 0v3z"/>
        </svg>
@endswitch
