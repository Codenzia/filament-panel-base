<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\DeviceTypeWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\VisitorsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Illuminate\Support\Facades\Schema;

it('ships the same analytics keys in every bundled locale', function (): void {
    $en = require __DIR__.'/../../resources/lang/en/analytics.php';
    $ar = require __DIR__.'/../../resources/lang/ar/analytics.php';

    expect(array_keys($ar))->toBe(array_keys($en));
});

it('translates the analytics chart copy with the active locale', function (): void {
    $widget = new VisitorsChartWidget;
    $widget->pageFilters = ['range' => '7d'];

    app()->setLocale('ar');

    // No analytics tables in this test's schema, so the widget reports the
    // "not migrated" branch — in Arabic.
    expect($widget->getEmptyStateHeading())
        ->toBe('لم تُنشأ جداول التحليلات')
        ->and($widget->getHeading())->toContain('مشاهدات الصفحات');
});

it('translates the selected range inside the chart heading', function (): void {
    $widget = new VisitorsChartWidget;
    $widget->pageFilters = ['range' => '30d'];

    app()->setLocale('ar');

    expect($widget->getHeading())->toBe('مشاهدات الصفحات — آخر 30 يومًا');
});

it('translates the device slice labels and falls back for unknown values', function (): void {
    (require __DIR__.'/../../database/migrations/analytics/create_visits_table.php')->up();

    foreach (['mobile', 'mobile', 'smartwatch'] as $deviceType) {
        Visit::create([
            'path' => '/',
            'method' => 'GET',
            'status' => 200,
            'is_bot' => false,
            'device_type' => $deviceType,
            'created_at' => now(),
        ]);
    }

    $widget = new DeviceTypeWidget;
    $widget->pageFilters = ['range' => '7d'];

    app()->setLocale('ar');

    $data = (fn (): array => $this->getData())->call($widget);

    expect($data['labels'])->toBe(['هاتف محمول', 'Smartwatch']);

    Schema::dropIfExists('visits');
});
