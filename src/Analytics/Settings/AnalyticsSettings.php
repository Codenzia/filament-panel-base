<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Settings;

use Codenzia\FilamentPanelBase\Analytics\AnalyticsPlugin;
use Spatie\LaravelSettings\Settings;

/**
 * Runtime-toggleable analytics settings. Resolution order:
 * fluent API (AnalyticsPlugin) → these settings → config defaults.
 *
 * @see AnalyticsPlugin
 */
class AnalyticsSettings extends Settings
{
    /** Master switch — when false, no writers run, no widgets query. */
    public bool $enabled = true;

    /** Record page-view rows via the TrackVisit middleware. */
    public bool $track_visits = true;

    /** Convert Laravel + package auth events into auth_events rows. */
    public bool $track_auth_events = true;

    /** Capture which Filament resource pages the user visits (folded into visits via route_name). */
    public bool $track_resource_usage = true;

    /**
     * IP anonymization mode:
     *  - 'none'     → store the raw IP (only do this if your legal posture allows it).
     *  - 'truncate' → zero out the last octet (IPv4) / last 80 bits (IPv6), then hash.
     *  - 'hash'     → SHA-256 of the raw IP. Cannot be reversed but still pseudonymous.
     */
    public string $ip_anonymization = 'truncate';

    /** Number of days to retain raw `visits` rows before pruning. */
    public int $retain_raw_days = 30;

    /** Number of days to retain `auth_events` rows before pruning. */
    public int $retain_aggregated_days = 365;

    /** When true, bot user agents are recorded with is_bot=true (excluded from default widgets). */
    public bool $bot_filter = true;

    /** Queue name for RecordVisitJob. Null = dispatch sync (test/dev). */
    public ?string $write_queue = null;

    /**
     * Widget keys allowed to render on AnalyticsPage / consumer panels.
     * Admins can prune this without redeploying.
     *
     * @var array<int, string>
     */
    public array $enabled_widgets = [
        'visitors_today',
        'visitors_chart',
        'top_pages',
        'auth_funnel',
        'failed_logins',
        'geo_map',
    ];

    public static function group(): string
    {
        return 'analytics';
    }
}
