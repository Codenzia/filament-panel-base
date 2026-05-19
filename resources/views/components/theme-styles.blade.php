{{-- Theme Styles: Injects runtime CSS custom properties into <head>.
     These variables power Tailwind utility classes (via the publishable
     theme.css @theme directive) and can be consumed by any custom CSS.

     Usage: <x-filament-panel-base::theme-styles />

     Or pass colors explicitly:
     <x-filament-panel-base::theme-styles :colors="$myColors" />

     Works with <x-filament-panel-base::dark-mode-script /> for FOUC prevention. --}}

@php
    $colors = $colors ?? \Codenzia\FilamentPanelBase\FilamentPanelBasePlugin::make()->getThemeColors();

    $primary = $colors['primary_color'] ?? '#3b82f6';
    $primaryHover = $colors['primary_hover_color'] ?? '#2563eb';
    $secondary = $colors['secondary_color'] ?? '#64748b';
    $secondaryHover = $colors['secondary_hover_color'] ?? '#475569';
    $background = $colors['background_color'] ?? '#ffffff';
    $surface = $colors['surface_color'] ?? '#f8fafc';
    $textPrimary = $colors['text_primary_color'] ?? '#1e293b';
    $textSecondary = $colors['text_secondary_color'] ?? '#64748b';
    $textOnPrimary = $colors['text_on_primary_color'] ?? '#ffffff';
    $success = $colors['success_color'] ?? '#22c55e';
    $warning = $colors['warning_color'] ?? '#f59e0b';
    $danger = $colors['danger_color'] ?? '#ef4444';
    $info = $colors['info_color'] ?? '#3b82f6';
    $border = $colors['border_color'] ?? '#e2e8f0';
    $shadow = $colors['shadow_color'] ?? 'rgba(0, 0, 0, 0.1)';

    $surfacePage = $colors['surface_page_color'] ?? '#f9fafb';
    $surfacePageDark = $colors['surface_page_dark_color'] ?? '#111827';
    $surfaceCard = $colors['surface_card_color'] ?? '#ffffff';
    $surfaceCardDark = $colors['surface_card_dark_color'] ?? '#1f2937';
    $surfaceInput = $colors['surface_input_color'] ?? '#ffffff';
    $surfaceInputDark = $colors['surface_input_dark_color'] ?? '#111827';
    $surfaceBorder = $colors['surface_border_color'] ?? '#d1d5db';
    $surfaceBorderDark = $colors['surface_border_dark_color'] ?? '#374151';
@endphp

<style>
    :root {
        /* Primary brand colors */
        --site-primary: {{ $primary }};
        --site-primary-hover: {{ $primaryHover }};

        /* Brand color scale via color-mix() — no rebuild needed */
        --site-brand-50: color-mix(in srgb, {{ $primary }} 7%, white);
        --site-brand-100: color-mix(in srgb, {{ $primary }} 13%, white);
        --site-brand-200: color-mix(in srgb, {{ $primary }} 23%, white);
        --site-brand-300: color-mix(in srgb, {{ $primary }} 38%, white);
        --site-brand-400: color-mix(in srgb, {{ $primary }} 55%, white);
        --site-brand-500: color-mix(in srgb, {{ $primary }} 78%, white);
        --site-brand-800: color-mix(in srgb, {{ $primary }} 72%, black);
        --site-brand-900: color-mix(in srgb, {{ $primary }} 50%, black);

        /* Semantic colors */
        --site-secondary: {{ $secondary }};
        --site-secondary-hover: {{ $secondaryHover }};
        --site-background: {{ $background }};
        --site-surface: {{ $surface }};
        --site-text-primary: {{ $textPrimary }};
        --site-text-secondary: {{ $textSecondary }};
        --site-text-on-primary: {{ $textOnPrimary }};
        --site-success: {{ $success }};
        --site-warning: {{ $warning }};
        --site-danger: {{ $danger }};
        --site-info: {{ $info }};
        --site-border: {{ $border }};
        --site-shadow: {{ $shadow }};

        /* Auth-page surfaces — overridable per consuming project */
        --site-surface-page: {{ $surfacePage }};
        --site-surface-page-dark: {{ $surfacePageDark }};
        --site-surface-card: {{ $surfaceCard }};
        --site-surface-card-dark: {{ $surfaceCardDark }};
        --site-surface-input: {{ $surfaceInput }};
        --site-surface-input-dark: {{ $surfaceInputDark }};
        --site-surface-border: {{ $surfaceBorder }};
        --site-surface-border-dark: {{ $surfaceBorderDark }};
    }
</style>
