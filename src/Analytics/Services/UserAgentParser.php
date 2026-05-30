<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Services;

/**
 * Lightweight UA parser. Returns a 3-tuple [deviceType, browser, platform]
 * with reasonable defaults. Intentionally regex-only — no external deps —
 * so the visit-tracking hot path stays cheap.
 *
 * Hosts that need full accuracy can bind their own implementation in the
 * container (key: `UserAgentParser::class`).
 */
class UserAgentParser
{
    /** @return array{device: ?string, browser: ?string, platform: ?string} */
    public function parse(?string $userAgent): array
    {
        if ($userAgent === null || $userAgent === '') {
            return ['device' => null, 'browser' => null, 'platform' => null];
        }

        $ua = $userAgent;

        return [
            'device' => $this->deviceType($ua),
            'browser' => $this->browser($ua),
            'platform' => $this->platform($ua),
        ];
    }

    private function deviceType(string $ua): string
    {
        $lc = strtolower($ua);

        if (str_contains($lc, 'tablet') || str_contains($lc, 'ipad')) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function browser(string $ua): ?string
    {
        // Check in order — Edge advertises itself as Chrome, Chrome as Safari, etc.
        $patterns = [
            'Edge' => '/Edg(?:e|A|iOS)?\/(\d+)/',
            'Chrome' => '/Chrome\/(\d+)/',
            'Firefox' => '/Firefox\/(\d+)/',
            'Safari' => '/Version\/(\d+).*Safari\//',
            'Opera' => '/OPR\/(\d+)/',
            'IE' => '/MSIE (\d+)|Trident.*rv:(\d+)/',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $ua, $matches)) {
                $version = $matches[1] ?? $matches[2] ?? '';

                return trim($name.' '.$version);
            }
        }

        return null;
    }

    private function platform(string $ua): ?string
    {
        if (preg_match('/Windows NT (\d+\.\d+)/', $ua, $m)) {
            return 'Windows '.$m[1];
        }

        if (preg_match('/Mac OS X (\d+[._]\d+(?:[._]\d+)?)/', $ua, $m)) {
            return 'macOS '.str_replace('_', '.', $m[1]);
        }

        if (preg_match('/Android (\d+(?:\.\d+)?)/', $ua, $m)) {
            return 'Android '.$m[1];
        }

        if (preg_match('/iPhone OS (\d+[._]\d+(?:[._]\d+)?)/', $ua, $m)) {
            return 'iOS '.str_replace('_', '.', $m[1]);
        }

        if (str_contains($ua, 'Linux')) {
            return 'Linux';
        }

        return null;
    }
}
