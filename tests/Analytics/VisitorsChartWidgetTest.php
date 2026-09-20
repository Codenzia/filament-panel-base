<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\VisitorsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;

/**
 * Filament shows a friendly "empty state" panel instead of the chart canvas
 * whenever getData() returns an empty array (isEmpty() checks that). That
 * only exists from Filament v4.13 / v5.x; on v4.11 an empty array reaches
 * Chart.js and the canvas renders blank, so those versions must keep
 * getting the zero-filled dataset with its axes.
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
class LegacyVisitorsChartWidget extends VisitorsChartWidget
{
    protected function supportsEmptyState(): bool
    {
        return false;
    }
}

function makeVisitorsChartWidget(string $range = '7d', string $class = VisitorsChartWidget::class): VisitorsChartWidget
{
    $widget = new $class;
    $widget->pageFilters = ['range' => $range];

    return $widget;
}

function visitorsChartData(VisitorsChartWidget $widget): array
{
    return (fn (): array => $this->getData())->call($widget);
}

it('returns an empty dataset when the visits table has no rows', function (): void {
    $widget = makeVisitorsChartWidget();

    expect(visitorsChartData($widget))->toBe([])
        ->and($widget->getEmptyStateHeading())->toBe(__('filament-panel-base::analytics.visitors_empty_heading'))
        ->and($widget->isEmpty())->toBeTrue();
});

it('returns an empty dataset when the visits table is missing entirely', function (): void {
    Schema::dropIfExists('visits');

    $widget = makeVisitorsChartWidget();

    expect(visitorsChartData($widget))->toBe([])
        ->and($widget->getEmptyStateHeading())->toBe(__('filament-panel-base::analytics.not_migrated_heading'));
});

it('returns real chart data once human visits exist in range', function (): void {
    Visit::create([
        'path' => '/',
        'method' => 'GET',
        'status' => 200,
        'is_bot' => false,
        'created_at' => now(),
    ]);

    $widget = makeVisitorsChartWidget();
    $data = visitorsChartData($widget);

    expect($data)->not->toBe([])
        ->and($data['datasets'][0]['label'])->toBe(__('filament-panel-base::analytics.visitors_dataset'))
        ->and(array_sum($data['datasets'][0]['data']))->toBe(1);
});

it('ignores bot visits when deciding whether the chart is empty', function (): void {
    Visit::create([
        'path' => '/',
        'method' => 'GET',
        'status' => 200,
        'is_bot' => true,
        'created_at' => now(),
    ]);

    $widget = makeVisitorsChartWidget();

    expect(visitorsChartData($widget))->toBe([]);
});

it('keeps a zero baseline with no rows on a Filament without the chart empty state', function (): void {
    $widget = makeVisitorsChartWidget('7d', LegacyVisitorsChartWidget::class);
    $data = visitorsChartData($widget);

    expect($data)->not->toBe([])
        ->and($data['labels'])->toHaveCount(7)
        ->and($data['datasets'][0]['data'])->toBe(array_fill(0, 7, 0));
});

it('keeps a zero baseline with no table on a Filament without the chart empty state', function (): void {
    Schema::dropIfExists('visits');

    $widget = makeVisitorsChartWidget('7d', LegacyVisitorsChartWidget::class);
    $data = visitorsChartData($widget);

    expect($data)->not->toBe([])
        ->and($data['labels'])->toHaveCount(7)
        ->and($data['datasets'][0]['data'])->toBe(array_fill(0, 7, 0));
});

it('detects the chart empty state on the installed Filament', function (): void {
    $supported = (fn (): bool => $this->supportsEmptyState())->call(makeVisitorsChartWidget());

    expect($supported)->toBe(method_exists(ChartWidget::class, 'isEmpty'));
});
