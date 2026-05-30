<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Services;

use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;

/**
 * Reduces a raw client IP to a 64-char SHA-256 hex digest under one of three
 * privacy modes. The result is always opaque to the database — even in
 * 'none' mode we hash so the column type stays stable across mode flips.
 *
 *  - none     → SHA-256(raw_ip).        Reversible only via brute force.
 *  - truncate → SHA-256(masked_ip).     Last octet (v4) / last 80 bits (v6) zeroed first.
 *  - hash     → SHA-256(raw_ip + salt). Strongest pseudonymity; uses APP_KEY.
 */
class IpAnonymizer
{
    public function __construct(private readonly AnalyticsSettings $settings) {}

    public function hash(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        $mode = $this->settings->ip_anonymization;

        $input = match ($mode) {
            'truncate' => $this->mask($ip),
            'hash' => $ip.config('app.key', ''),
            default => $ip,
        };

        return hash('sha256', $input);
    }

    private function mask(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Mask to the /48 prefix — strips the last 80 bits.
            $packed = inet_pton($ip);

            if ($packed === false) {
                return $ip;
            }

            // 6 bytes prefix + 10 bytes of zeros.
            $masked = substr($packed, 0, 6).str_repeat("\0", 10);
            $unpacked = inet_ntop($masked);

            return $unpacked === false ? $ip : $unpacked;
        }

        return $ip;
    }
}
