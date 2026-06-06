<?php

use Codenzia\FilamentPanelBase\Auth\Rules\AllowedEmailDomain;

beforeEach(function (): void {
    // No AuthenticationSettings in the container during these unit tests, so
    // the rule falls back to the config-file allowlist.
    config(['filament-panel-base.allowed_email_domains' => ['acme.com']]);
});

it('allows an email on the permitted domain', function (): void {
    $failed = false;
    (new AllowedEmailDomain)->validate('email', 'jo@acme.com', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('allows a subdomain of a permitted domain', function (): void {
    $failed = false;
    (new AllowedEmailDomain)->validate('email', 'jo@eu.acme.com', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('rejects an email outside the allowlist', function (): void {
    $failed = false;
    (new AllowedEmailDomain)->validate('email', 'jo@gmail.com', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('does not match a look-alike suffix domain', function (): void {
    // notacme.com must NOT pass just because it ends with "acme.com".
    $failed = false;
    (new AllowedEmailDomain)->validate('email', 'jo@notacme.com', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('allows any domain when the allowlist is empty', function (): void {
    config(['filament-panel-base.allowed_email_domains' => []]);

    $failed = false;
    (new AllowedEmailDomain)->validate('email', 'jo@anything.test', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('tolerates a leading @ and mixed case in the allowlist', function (): void {
    config(['filament-panel-base.allowed_email_domains' => ['@Acme.com']]);

    $failed = false;
    (new AllowedEmailDomain)->validate('email', 'JO@ACME.COM', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});
