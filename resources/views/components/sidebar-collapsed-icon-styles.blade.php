{{-- Collapsed icon-only sidebar polish — injected via HEAD_END whenever
     the panel uses Filament's collapsibleOnDesktop mode AND sidebarCollapseToIcons is on.
     Independent of slideover; applies when the sidebar is shown as a narrow icon strip.

     Visual contract:
       - hidden scrollbar
       - rounded pill container around the group list
       - circular icon buttons
       - primary-gradient active item
--}}

<style>
    @media (min-width: 64rem) {
        /* Constrain the sidebar to a narrow icon-only strip when collapsed.
           Filament v4's default leaves the sidebar at its open width — we shrink it
           so the pill container hugs the icons instead of floating inside a wide bar. */
        .fi-sidebar:not(.fi-sidebar-open) {
            width: 5rem !important;
            min-width: 5rem;
            overflow-x: hidden;
        }

        /* Hide labels & group headers when collapsed so only icons show. */
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-label,
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group-label,
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-group-collapse-btn {
            display: none;
        }

        /* Hide scrollbar when collapsed to icons, and constrain the nav to the
           collapsed strip width. Filament leaves the inner nav at its expanded
           width, so without this the centred pill is laid out in a ~16rem box and
           its right edge is clipped by the 5rem overflow-hidden sidebar. */
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav {
            width: 5rem;
            box-sizing: border-box;
            padding-inline: 0;
            scrollbar-width: none;          /* Firefox */
            -ms-overflow-style: none;       /* IE/Edge */
        }

        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav::-webkit-scrollbar {
            display: none;                  /* Chrome/Safari */
        }

        /* Pill container around the nav group list — sized to its icons and
           centred in the strip so the side gaps are even, with vertical
           breathing room between the circular buttons. */
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups {
            border-radius: 30px;
            padding: 14px 12px;
            row-gap: 8px !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: fit-content;
            margin-inline: auto;
            background: #fff;
            border: 1px solid var(--color-gray-200, #e2e8f0);
        }

        /* Centre each item (and its circular button) within the pill. */
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        html.dark .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups {
            background-color: var(--color-gray-900, #0f172a);
            border-color: var(--color-gray-800, #334155);
        }

        /* Keep one sidebar surface across both states: the expanded sidebar uses
           the same background as the collapsed pill, so toggling collapse/expand
           doesn't shift the colour. */
        .fi-sidebar.fi-sidebar-open {
            background-color: #fff;
        }

        html.dark .fi-sidebar.fi-sidebar-open {
            background-color: var(--color-gray-900, #0f172a);
        }

        /* All nav buttons circular when collapsed.
           Explicit width/height so border-radius:100% renders a circle, not an oval —
           in slideover-off mode the button would otherwise stretch to row width. */
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn {
            width: 2.5rem;
            height: 2.5rem;
            padding: 0;
            margin: 0 auto;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
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

        /* Circular collapse/expand chevron toggles — sidebar header + topbar.
           Scoped to Filament's four chevron-button classes (identical in v4 and
           v5), NOT every .fi-icon-btn; the mobile hamburger/X keeps its default
           look. Solid fill from the gray ramp, a 1px primary ring and a soft
           primary glow via color-mix on Filament's --primary-* color variables
           so it self-adapts to each app's brand. No display override here:
           these buttons rely on x-cloak/x-show, so their display is Alpine's. */
        .fi-sidebar-open-collapse-sidebar-btn,
        .fi-sidebar-close-collapse-sidebar-btn,
        .fi-topbar-open-collapse-sidebar-btn,
        .fi-topbar-close-collapse-sidebar-btn {
            width: 2.25rem;
            height: 2.25rem;
            padding: 0;
            flex-shrink: 0;
            border-radius: 9999px;
            background-color: color-mix(in srgb, var(--primary-500, #0ea5e9) 10%, #fff);
            color: var(--primary-600, #0284c7);
            box-shadow:
                0 0 0 1px color-mix(in srgb, var(--primary-500, #0ea5e9) 25%, transparent),
                0 0 12px color-mix(in srgb, var(--primary-400, #38bdf8) 15%, transparent);
            transition: box-shadow 150ms ease, background-color 150ms ease;
        }

        .fi-sidebar-open-collapse-sidebar-btn:hover,
        .fi-sidebar-close-collapse-sidebar-btn:hover,
        .fi-topbar-open-collapse-sidebar-btn:hover,
        .fi-topbar-close-collapse-sidebar-btn:hover {
            background-color: color-mix(in srgb, var(--primary-500, #0ea5e9) 18%, #fff);
            box-shadow:
                0 0 0 1px color-mix(in srgb, var(--primary-500, #0ea5e9) 32%, transparent),
                0 0 14px color-mix(in srgb, var(--primary-400, #38bdf8) 20%, transparent);
        }

        html.dark .fi-sidebar-open-collapse-sidebar-btn,
        html.dark .fi-sidebar-close-collapse-sidebar-btn,
        html.dark .fi-topbar-open-collapse-sidebar-btn,
        html.dark .fi-topbar-close-collapse-sidebar-btn {
            background-color: color-mix(in srgb, var(--primary-500, #0ea5e9) 18%, var(--color-gray-900, #0f172a));
            color: var(--primary-400, #38bdf8);
            box-shadow:
                0 0 0 1px color-mix(in srgb, var(--primary-400, #38bdf8) 30%, transparent),
                0 0 12px color-mix(in srgb, var(--primary-400, #38bdf8) 25%, transparent);
        }

        html.dark .fi-sidebar-open-collapse-sidebar-btn:hover,
        html.dark .fi-sidebar-close-collapse-sidebar-btn:hover,
        html.dark .fi-topbar-open-collapse-sidebar-btn:hover,
        html.dark .fi-topbar-close-collapse-sidebar-btn:hover {
            background-color: color-mix(in srgb, var(--primary-500, #0ea5e9) 28%, var(--color-gray-900, #0f172a));
            box-shadow:
                0 0 0 1px color-mix(in srgb, var(--primary-400, #38bdf8) 45%, transparent),
                0 0 14px color-mix(in srgb, var(--primary-400, #38bdf8) 35%, transparent);
        }
    }
</style>
