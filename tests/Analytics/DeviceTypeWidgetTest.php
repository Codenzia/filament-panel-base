<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\DeviceTypeWidget;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Illuminate\Support\Facades\Schema;

/**
 * Same empty-state contract as VisitorsChartWidgetTest. Before this change,
 * a table with zero rows still produced a non-empty top-level array (empty
 * inner "data"/"labels"), so Filament's isEmpty() (which just checks
 * empty(getData())) never tripped and the doughnut rendered blank instead of
 * showing the empty state. Filament versions without isEmpty() keep the old
 * array, because [] would leave them with a blank canvas.
 */
beforeEach(function (): void {
    (require __DIR__.'/../../database/migrations/analytics/create_visits_table.php')->up();
});

afterEach(function (): void {
    Schema::dropIfExists('visits');
});

/**
 * Stands in for a Filament release with no ChartWidget::isEmpty().
 */
class LegacyDeviceTypeWidget extends DeviceTypeWidget
{
    protected function supportsEmptyState(): bool
    {
        return false;
    }
}

function makeDeviceTypeWidget(string $range = '7d', string $class = DeviceTypeWidget::class): DeviceTypeWidget
{
    $widget = new $class;
    $widget->pageFilters = ['range' => $range];

    return $widget;
}

function deviceTypeChartData(DeviceTypeWidget $widget): array
{
    return (fn (): array => $this->getData())->call($widget);
}

it('returns an empty dataset when the visits table has no rows', function (): void {
    $widget = makeDeviceTypeWidget();

    expect(deviceTypeChartData($widget))->toBe([])
        ->and($widget->getEmptyStateHeading())->toBe(__('filament-panel-base::analytics.devices_empty_heading'));
});

it('returns an empty dataset when the visits table is missing entirely', function (): void {
    Schema::dropIfExists('visits');

    $widget = makeDeviceTypeWidget();

    expect(deviceTypeChartData($widget))->toBe([])
        ->and($widget->getEmptyStateHeading())->toBe(__('filament-panel-base::analytics.not_migrated_heading'));
});

it('returns a device breakdown once human visits exist in range', function (): void {
    Visit::create([
        'path' => '/',
        'method' => 'GET',
        'status' => 200,
        'is_bot' => false,
        'device_type' => 'mobile',
        'created_at' => now(),
    ]);

    $widget = makeDeviceTypeWidget();
    $data = deviceTypeChartData($widget);

    expect($data)->not->toBe([])
        ->and($data['labels'])->toBe(['Mobile'])
        ->and($data['datasets'][0]['data'])->toBe([1]);
});

it('keeps the empty slice arrays on a Filament without the chart empty state', function (): void {
    $widget = makeDeviceTypeWidget('7d', LegacyDeviceTypeWidget::class);
    $data = deviceTypeChartData($widget);

    expect($data)->not->toBe([])
        ->and($data['labels'])->toBe([])
        ->and($data['datasets'][0]['data'])->toBe([]);
});

it('keeps the empty slice arrays with no table on a Filament without the chart empty state', function (): void {
    Schema::dropIfExists('visits');

    $widget = makeDeviceTypeWidget('7d', LegacyDeviceTypeWidget::class);

    expect(deviceTypeChartData($widget))->toBe([
        'datasets' => [['data' => [], 'backgroundColor' => []]],
        'labels' => [],
    ]);
});
