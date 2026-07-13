<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Analytics\Services\BotDetector;

/**
 * BotDetector runs on every page view (hot path) and decides whether a hit
 * is excluded from the default widgets. These tests pin the empty-UA rule,
 * case-insensitivity, and that a normal browser UA is NOT flagged.
 */
beforeEach(function (): void {
    $this->detector = new BotDetector;
});

it('treats a null or empty user agent as a bot', function (): void {
    expect($this->detector->isBot(null))->toBeTrue()
        ->and($this->detector->isBot(''))->toBeTrue();
});

it('flags every configured signature, case-insensitively', function (): void {
    foreach (BotDetector::SIGNATURES as $needle) {
        // Wrap the needle in surrounding text and upper-case it to prove the
        // match is substring + case-insensitive.
        $ua = 'Mozilla/5.0 '.strtoupper($needle).' extra';

        expect($this->detector->isBot($ua))->toBeTrue("failed to flag '{$needle}'");
    }
});

it('does not flag a normal desktop browser user agent', function (): void {
    $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

    expect($this->detector->isBot($chrome))->toBeFalse();
});

it('does not flag a normal mobile browser user agent', function (): void {
    $safariMobile = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) '
        .'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    expect($this->detector->isBot($safariMobile))->toBeFalse();
});

it('flags common crawlers by name', function (): void {
    $googlebot = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
    $bingbot = 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)';

    expect($this->detector->isBot($googlebot))->toBeTrue()
        ->and($this->detector->isBot($bingbot))->toBeTrue();
});

it('flags scripted HTTP clients (curl / wget / python-requests)', function (): void {
    expect($this->detector->isBot('curl/8.4.0'))->toBeTrue()
        ->and($this->detector->isBot('Wget/1.21.3'))->toBeTrue()
        ->and($this->detector->isBot('python-requests/2.31.0'))->toBeTrue();
});
