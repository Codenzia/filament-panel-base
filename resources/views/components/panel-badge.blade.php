{{-- Panel title badge — injected via render hooks (TOPBAR_LOGO_AFTER, AUTH_LOGIN/REGISTER_FORM_BEFORE).
     Props: $label (string), $color (string), $icon (?string), $centered (bool) --}}

@props([
    'label' => '',
    'color' => 'primary',
    'icon' => null,
    'centered' => false,
])

@php
    $colorClasses = [
        'primary' => 'bg-primary-100 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/30',
        'success' => 'bg-success-100 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-400/30',
        'warning' => 'bg-warning-100 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-400/30',
        'danger'  => 'bg-danger-100 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/30',
        'info'    => 'bg-info-100 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-400 dark:ring-info-400/30',
        'gray'    => 'bg-gray-100 text-gray-700 ring-gray-600/20 dark:bg-gray-500/10 dark:text-gray-400 dark:ring-gray-400/30',
    ];

    $classes = $colorClasses[$color] ?? $colorClasses['primary'];
@endphp

@if($centered)
    <div class="flex justify-center mb-4">
        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $classes }}">
            @if($icon) @svg($icon, 'w-5 h-5 mx-1.5') @endif
            {{ $label }}
        </span>
    </div>
@else
    <span class="mx-4 inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $classes }}">
        @if($icon) @svg($icon, 'w-5 h-5 mx-1.5') @endif
        {{ $label }}
    </span>
@endif
