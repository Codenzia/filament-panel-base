<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\FailedLoginsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Illuminate\Support\Facades\Schema;

/**
 * Same empty-state contract as VisitorsChartWidgetTest: getData() must
 * return [] (not a zero-filled dataset) whenever there is nothing to plot,
 * so Filament's isEmpty() check swaps the canvas for the friendly
 * empty-state panel instead of a flat baseline chart. Filament versions
 * without isEmpty() keep the zero-filled dataset, because [] would leave
 * them with a blank canvas.
 */
beforeEach(function (): void {
    (require __DIR__.'/../../database/migrations/analytics/create_auth_events_table.php')->up();
});

afterEach(function (): void {
    Schema::dropIfExists('auth_events');
});

/**
 * Stands in for a Filament release with no ChartWidget::isEmpty().
 */
class LegacyFailedLoginsChartWidget extends FailedLoginsChartWidget
{
    protected function supportsEmptyState(): bool
    {
        return false;
    }
}

function makeFailedLoginsChartWidget(string $range = '7d', string $class = FailedLoginsChartWidget::class): FailedLoginsChartWidget
{
    $widget = new $class;
    $widget->pageFilters = ['range' => $range];

    return $widget;
}

function failedLoginsChartData(FailedLoginsChartWidget $widget): array
{
    return (fn (): array => $this->getData())->call($widget);
}

it('returns an empty dataset when there are no failed logins in range', function (): void {
    $widget = makeFailedLoginsChartWidget();

    expect(failedLoginsChartData($widget))->toBe([])
        ->and($widget->getEmptyStateHeading())->toBe(__('filament-panel-base::analytics.failed_logins_empty_heading'));
});

it('returns an empty dataset when the auth_events table is missing entirely', function (): void {
    Schema::dropIfExists('auth_events');

    $widget = makeFailedLoginsChartWidget();

    expect(failedLoginsChartData($widget))->toBe([])
        ->and($widget->getEmptyStateHeading())->toBe(__('filament-panel-base::analytics.not_migrated_heading'));
});

it('returns real chart data once failed logins exist in range', function (): void {
    AuthEvent::create([
        'type' => AuthEvent::TYPE_LOGIN_FAILED,
        'created_at' => now(),
    ]);

    $widget = makeFailedLoginsChartWidget();
    $data = failedLoginsChartData($widget);

    expect($data)->not->toBe([])
        ->and($data['datasets'][0]['label'])->toBe(__('filament-panel-base::analytics.failed_logins_dataset'))
        ->and(array_sum($data['datasets'][0]['data']))->toBe(1);
});

it('ignores successful logins when deciding whether the chart is empty', function (): void {
    AuthEvent::create([
        'type' => AuthEvent::TYPE_LOGIN_SUCCESS,
        'created_at' => now(),
    ]);

    $widget = makeFailedLoginsChartWidget();

    expect(failedLoginsChartData($widget))->toBe([]);
});

it('keeps a zero baseline with no failed logins on a Filament without the chart empty state', function (): void {
    $widget = makeFailedLoginsChartWidget('7d', LegacyFailedLoginsChartWidget::class);
    $data = failedLoginsChartData($widget);

    expect($data)->not->toBe([])
        ->and($data['labels'])->toHaveCount(7)
        ->and($data['datasets'][0]['data'])->toBe(array_fill(0, 7, 0));
});

it('keeps a zero baseline with no table on a Filament without the chart empty state', function (): void {
    Schema::dropIfExists('auth_events');

    $widget = makeFailedLoginsChartWidget('7d', LegacyFailedLoginsChartWidget::class);
    $data = failedLoginsChartData($widget);

    expect($data)->not->toBe([])
        ->and($data['labels'])->toHaveCount(7)
        ->and($data['datasets'][0]['data'])->toBe(array_fill(0, 7, 0));
});
