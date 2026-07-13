<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Services\IpAnonymizer;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;

/**
 * The IpAnonymizer is the module's PII boundary — the raw client IP must
 * never reach the database. These tests pin each privacy mode (none /
 * truncate / hash) plus the null/empty and invalid-IP edge cases.
 */
function anonymizer(string $mode): IpAnonymizer
{
    /** @var AnalyticsSettings $settings */
    $settings = test()->settingsStub(AnalyticsSettings::class);
    $settings->ip_anonymization = $mode;

    return new IpAnonymizer($settings);
}

it('returns null for a null or empty IP (no digest of nothing)', function (): void {
    expect(anonymizer('truncate')->hash(null))->toBeNull()
        ->and(anonymizer('truncate')->hash(''))->toBeNull();
});

it('always returns an opaque 64-char sha-256 digest, never the raw IP', function (): void {
    foreach (['none', 'truncate', 'hash'] as $mode) {
        $out = anonymizer($mode)->hash('203.0.113.7');

        expect($out)->toBeString()
            ->and(strlen($out))->toBe(64)
            ->and($out)->not->toContain('203.0.113');
    }
});

it('none mode hashes the raw IP verbatim', function (): void {
    expect(anonymizer('none')->hash('203.0.113.7'))
        ->toBe(hash('sha256', '203.0.113.7'));
});

it('truncate mode zeroes the last IPv4 octet before hashing', function (): void {
    // .7 and .200 in the same /24 must collapse to the same digest…
    $a = anonymizer('truncate')->hash('203.0.113.7');
    $b = anonymizer('truncate')->hash('203.0.113.200');

    expect($a)->toBe($b)
        ->and($a)->toBe(hash('sha256', '203.0.113.0'));

    // …but a different /24 must not.
    expect(anonymizer('truncate')->hash('203.0.114.7'))->not->toBe($a);
});

it('truncate mode masks IPv6 to the /48 prefix before hashing', function (): void {
    // Same /48, different low bits → same digest.
    $a = anonymizer('truncate')->hash('2001:db8:abcd:1111:2222:3333:4444:5555');
    $b = anonymizer('truncate')->hash('2001:db8:abcd:ffff:ffff:ffff:ffff:ffff');

    expect($a)->toBe($b);

    // Different /48 → different digest.
    expect(anonymizer('truncate')->hash('2001:db8:abce:1111:2222:3333:4444:5555'))
        ->not->toBe($a);
});

it('hash mode salts with the app key so the digest is not a bare sha-256 of the IP', function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

    $salted = anonymizer('hash')->hash('203.0.113.7');

    expect($salted)->toBe(hash('sha256', '203.0.113.7'.config('app.key')))
        ->and($salted)->not->toBe(hash('sha256', '203.0.113.7'));
});

it('is deterministic for the same IP + mode', function (): void {
    expect(anonymizer('truncate')->hash('198.51.100.9'))
        ->toBe(anonymizer('truncate')->hash('198.51.100.9'));
});

it('passes an unparseable IP through truncation unchanged (then hashes it)', function (): void {
    // Not a valid IP → mask() returns it as-is, still hashed (never stored raw).
    expect(anonymizer('truncate')->hash('not-an-ip'))
        ->toBe(hash('sha256', 'not-an-ip'));
});
