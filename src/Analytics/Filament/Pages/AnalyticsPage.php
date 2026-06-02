<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Filament\Pages;

use BackedEnum;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\AuthFunnelWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\DeviceTypeWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\ErrorRateSparklineWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\FailedLoginsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\GeoBreakdownWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\SlowestPagesWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\TopPagesWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\VisitorsChartWidget;
use Codenzia\FilamentPanelBase\Analytics\Filament\Widgets\VisitorsTodayWidget;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Schemas\Schema;

/**
 * Filament page that hosts the analytics widgets. Subclasses
 * `Filament\Pages\Dashboard` so the widget grid layout, refresh, and column
 * controls all come for free.
 *
 * Mounted on a panel via
 * `FilamentPanelBasePlugin::make()->withFilamentAnalyticsPage()`. Hosts that
 * need authorisation (Shield, role gate) subclass this page and override
 * `canAccess()` — same pattern as ManageAuthenticationSettings.
 */
class AnalyticsPage extends Dashboard
{
    protected static ?string $slug = 'analytics';

    // Dashboard::getRoutePath() returns $routePath (NOT $slug); without this the
    // page inherits the default '/' and collides with the panel's home dashboard,
    // so its route is never registered.
    protected static string $routePath = 'analytics';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics';

    protected static ?int $navigationSort = 90;

    /**
     * Filament 4 supports getNavigationGroup() — kept as a string so callers
     * can swap the constant via translation files.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('Insights');
    }

    /**
     * Default: visible to any authenticated user. Tighten via subclass +
     * Shield (`HasPageShield`) or a host-side role check.
     */
    public static function canAccess(): bool
    {
        return filament()->auth()->check();
    }

    /**
     * Widgets shown on the page. Order matters — first widget is rendered
     * full-width, subsequent widgets fill columns left-to-right.
     *
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return [
            VisitorsTodayWidget::class,
            ErrorRateSparklineWidget::class,
            VisitorsChartWidget::class,
            AuthFunnelWidget::class,
            FailedLoginsChartWidget::class,
            TopPagesWidget::class,
            SlowestPagesWidget::class,
            GeoBreakdownWidget::class,
            DeviceTypeWidget::class,
        ];
    }

    /**
     * @return int|array<string, int|null>
     */
    public function getColumns(): int|array
    {
        return 2;
    }

    /**
     * Default filter state — populated once on mount, then carried in
     * `$this->filters` and propagated to widgets via Filament's
     * InteractsWithPageFilters trait.
     *
     * @var array<string, mixed>|null
     */
    public ?array $filters = ['range' => '7d'];

    /**
     * Range picker shown above the widget grid. Filament's Dashboard base
     * picks up this method automatically and emits the form via the
     * EmbeddedSchema content component. Widgets consume it through
     * `$this->pageFilters['range']`.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('range')
                    ->label(__('Date range'))
                    ->options([
                        '24h' => __('Last 24 hours'),
                        '7d' => __('Last 7 days'),
                        '30d' => __('Last 30 days'),
                        '90d' => __('Last 90 days'),
                    ])
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),
            ]);
    }
}
