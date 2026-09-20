<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\Concerns;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;

/**
 * Empty-state plumbing shared by the analytics chart widgets.
 *
 * Filament swaps the chart canvas for the getEmptyState* trio whenever
 * getData() returns an empty array — but only from v4.13 / v5.x, where
 * ChartWidget::isEmpty(), the HasEmptyState concern and the blade block
 * behind them were added. On v4.11 an empty array reaches Chart.js
 * unguarded and the canvas renders blank, so those versions keep the
 * zero-filled dataset that draws a flat baseline with its axes.
 *
 * Whether the widget's table exists is asked by the description, all three
 * empty-state methods and getData(), so the answer is cached per instance:
 * one metadata query per poll instead of five.
 */
trait HandlesChartEmptyState
{
    private ?bool $analyticsTableExists = null;

    /**
     * The analytics table this widget plots.
     */
    abstract protected function analyticsTable(): string;

    protected function hasAnalyticsTable(): bool
    {
        return $this->analyticsTableExists ??= Schema::hasTable($this->analyticsTable());
    }

    protected function supportsEmptyState(): bool
    {
        return method_exists(ChartWidget::class, 'isEmpty');
    }
}
