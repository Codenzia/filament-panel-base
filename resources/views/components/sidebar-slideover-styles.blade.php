{{-- Sidebar slide-over styles & script — injected via HEAD_END render hook.
     Overrides Filament's desktop sidebar to overlay content instead of pushing it,
     with polish animations (slide, frosted backdrop, content scale-down, nav stagger).
     Props: $collapseToIcons (bool) --}}

@props([
    'collapseToIcons' => false,
])

<style>
    /* 1. Sidebar slide — desktop only.
       translateX(-100%) is skipped when collapseToIcons is on,
       so Filament's built-in icon-only narrow state shows instead. */
    @media (min-width: 64rem) {
        .fi-sidebar {
            transition: transform .3s cubic-bezier(.4, 0, .2, 1);
        }

        @unless ($collapseToIcons)
            .fi-sidebar:not(.fi-sidebar-open) {
                transform: translateX(-100%);
            }
        @endunless
    }

    /* 2. Sidebar open state — position/z-index/shadow at all sizes.
       Background overridden only on desktop (lg) to counteract
       Filament's lg:bg-transparent. On mobile, Tailwind's own
       bg-white / dark:bg-gray-900 already matches the topbar. */
    .fi-sidebar.fi-sidebar-open {
        position: fixed !important;
        z-index: 40;
        box-shadow: 4px 0 24px rgba(0, 0, 0, .12);
    }

    html.dark .fi-sidebar.fi-sidebar-open {
        box-shadow: 4px 0 24px rgba(0, 0, 0, .4);
    }

    @media (min-width: 64rem) {
        .fi-sidebar.fi-sidebar-open {
            background-color: var(--color-white, #fff);
        }

        html.dark .fi-sidebar.fi-sidebar-open {
            background-color: var(--color-gray-900, #0f172a);
        }
    }

    /* 2b. Collapsed icon strip — pill container on nav-groups, circular buttons. */
    @if ($collapseToIcons)
        @media (min-width: 64rem) {
            /* Pill container around the nav group list */
            .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups {
                border-radius: 30px;
                padding: 12px 8px;
                row-gap: 0 !important;
                background: #fff;
                border: 1px solid var(--color-gray-200, #e2e8f0);
            }

            html.dark .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups {
                background-color: var(--color-gray-900, #0f172a);
                border-color: var(--color-gray-700, #334155);
            }

            /* All nav buttons circular when collapsed */
            .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn {
                border-radius: 100%;
            }

            .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item:hover {
                border-radius: 50% !important;
            }

            /* Active item — primary gradient circle */
            .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
                background: linear-gradient(to right, var(--primary-600), var(--primary-400)) !important;
            }

            .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active svg,
            .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item.fi-active span {
                color: #fff;
            }

            .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-active > * {
                background-color: transparent !important;
            }
        }
    @endif

    /* 3. Topbar raised above the backdrop. */
    .fi-topbar-ctn {
        z-index: 35;
    }

    /* 4. Frosted glass overlay — reduce Filament's opaque dark bg so the blur is visible.
       No !important on display — Alpine x-show still controls it via inline style. */
    .fi-sidebar-close-overlay {
        transition: opacity .3s ease;
        background-color: rgba(0, 0, 0, .35) !important;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }

    @media (min-width: 64rem) {
        .fi-sidebar-close-overlay {
            display: block;
        }
    }

    /* 5. Content scale-down — desktop only to avoid affecting mobile layout.
       scale(.95) and blur(2px) are perceptible; .98/1px were too subtle. */
    @media (min-width: 64rem) {
        .fi-main-ctn {
            transition: transform .3s cubic-bezier(.4, 0, .2, 1), filter .3s ease;
        }

        .fi-sidebar.fi-sidebar-open~.fi-main-ctn {
            transform: scale(.95);
            filter: blur(2px);
        }
    }

    /* 6. Nav items stagger — each fi-sidebar-group cascades in with a slight delay.
       Target fi-sidebar-nav-groups>li (the groups), NOT fi-sidebar-nav>* (the ul).
       animation-fill-mode:both keeps items hidden during delay, holds final state. */
    @keyframes fi-nav-in {
        from {
            opacity: 0;
            transform: translateX(-.75rem);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li {
        animation: fi-nav-in .35s ease both;
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li:nth-child(1) {
        animation-delay: .09s;
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li:nth-child(2) {
        animation-delay: .18s;
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li:nth-child(3) {
        animation-delay: .27s;
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li:nth-child(4) {
        animation-delay: .36s;
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li:nth-child(5) {
        animation-delay: .45s;
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li:nth-child(6) {
        animation-delay: .54s;
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li:nth-child(7) {
        animation-delay: .63s;
    }

    .fi-sidebar.fi-sidebar-open .fi-sidebar-nav-groups>li:nth-child(8) {
        animation-delay: .72s;
    }
</style>

{{-- Mirror Filament's own mobile behavior:
     x-on:click="window.matchMedia('(max-width:1024px)').matches && $store.sidebar.close()"
     Filament only closes on mobile. We extend that to desktop in slideover mode.
     close() also sets isOpenDesktop=false via Alpine.$persist so the state
     survives wire:navigate without any livewire:navigated timing hacks. --}}
<script>
    document.addEventListener("click", function(e) {
        if (window.innerWidth < 1024) return;
        if (!e.target.closest(".fi-sidebar-nav a")) return;
        var s = window.Alpine && window.Alpine.store("sidebar");
        if (s && s.isOpen) s.close();
    });
</script>
