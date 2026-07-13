<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Services\UserAgentParser;

/**
 * Pure regex UA parser on the visit-tracking hot path. Pins device / browser
 * / platform detection and the empty-UA null shape.
 */
beforeEach(function (): void {
    $this->parser = new UserAgentParser;
});

it('returns all-null for a null or empty user agent', function (): void {
    expect($this->parser->parse(null))
        ->toBe(['device' => null, 'browser' => null, 'platform' => null])
        ->and($this->parser->parse(''))
        ->toBe(['device' => null, 'browser' => null, 'platform' => null]);
});

it('detects a Windows Chrome desktop', function (): void {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

    expect($this->parser->parse($ua))->toMatchArray([
        'device' => 'desktop',
        'browser' => 'Chrome 125',
        'platform' => 'Windows 10.0',
    ]);
});

it('detects an iPhone Safari as mobile iOS', function (): void {
    $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) '
        .'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    expect($this->parser->parse($ua))->toMatchArray([
        'device' => 'mobile',
        'browser' => 'Safari 17',
        'platform' => 'iOS 17.5',
    ]);
});

it('detects an iPad as a tablet', function (): void {
    $ua = 'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 '
        .'(KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    expect($this->parser->parse($ua)['device'])->toBe('tablet');
});

it('detects Android Firefox as mobile', function (): void {
    $ua = 'Mozilla/5.0 (Android 14; Mobile; rv:126.0) Gecko/126.0 Firefox/126.0';

    expect($this->parser->parse($ua))->toMatchArray([
        'device' => 'mobile',
        'browser' => 'Firefox 126',
        'platform' => 'Android 14',
    ]);
});

it('detects Edge ahead of Chrome (Edge advertises Chrome too)', function (): void {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36 Edg/125.0.0.0';

    expect($this->parser->parse($ua)['browser'])->toBe('Edge 125');
});

it('falls back to desktop with null browser/platform for an unknown UA', function (): void {
    expect($this->parser->parse('SomeRandomAgent/1.0'))->toMatchArray([
        'device' => 'desktop',
        'browser' => null,
        'platform' => null,
    ]);
});
