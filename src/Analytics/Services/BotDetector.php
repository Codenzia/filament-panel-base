<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Services;

/**
 * Lightweight bot detection by user-agent substring match. Intentionally
 * not bundled with a heavy crawler-commons dataset — the hot path runs on
 * every page view, so this stays a fast string scan.
 *
 * Adopt the optional `jenssegers/agent` package via composer suggest and
 * a host-side decorator if you need full crawler-list accuracy.
 */
class BotDetector
{
    /**
     * Common substrings found in bot user agents. Case-insensitive.
     *
     * @var array<int, string>
     */
    public const SIGNATURES = [
        'bot',
        'crawler',
        'spider',
        'slurp',
        'mediapartners',
        'curl/',
        'wget/',
        'python-requests',
        'okhttp/',
        'go-http-client',
        'httpclient',
        'headlesschrome',
        'phantomjs',
        'lighthouse',
        'facebookexternalhit',
        'whatsapp',
        'telegrambot',
        'discordbot',
        'pingdom',
        'uptimerobot',
        'monitis',
        'newrelic',
        'datadog',
    ];

    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            // Empty UA → treat as bot (browsers always send one).
            return true;
        }

        $ua = strtolower($userAgent);

        foreach (self::SIGNATURES as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
    }
}
