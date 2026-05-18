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

        /* Hide scrollbar when collapsed to icons */
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav {
            scrollbar-width: none;          /* Firefox */
            -ms-overflow-style: none;       /* IE/Edge */
        }

        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav::-webkit-scrollbar {
            display: none;                  /* Chrome/Safari */
        }

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
            border-color: var(--color-gray-800, #334155);
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
    }
</style>
