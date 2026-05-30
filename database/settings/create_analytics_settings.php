<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = [
            'analytics.enabled' => true,
            'analytics.track_visits' => true,
            'analytics.track_auth_events' => true,
            'analytics.track_resource_usage' => true,
            'analytics.ip_anonymization' => 'truncate',
            'analytics.retain_raw_days' => 30,
            'analytics.retain_aggregated_days' => 365,
            'analytics.bot_filter' => true,
            'analytics.write_queue' => null,
            'analytics.enabled_widgets' => [
                'visitors_today',
                'visitors_chart',
                'top_pages',
                'auth_funnel',
                'failed_logins',
                'geo_map',
            ],
        ];

        foreach ($defaults as $key => $value) {
            if (! $this->migrator->exists($key)) {
                $this->migrator->add($key, $value);
            }
        }
    }
};
