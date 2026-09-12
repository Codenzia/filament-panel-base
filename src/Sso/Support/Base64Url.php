<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Support;

/**
 * base64url (RFC 7515 §2) — the encoding every JOSE value uses: standard
 * base64 with `+/` swapped for `-_` and the `=` padding stripped.
 */
final class Base64Url
{
    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Returns null on malformed input rather than a silently truncated string,
     * so callers can reject a tampered token instead of verifying garbage.
     */
    public static function decode(string $value): ?string
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
