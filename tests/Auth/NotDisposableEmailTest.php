<?php

use Codenzia\FilamentPanelBase\Auth\Rules\NotDisposableEmail;

beforeEach(function (): void {
    config(['disposable_emails.enabled' => true]);
    config(['disposable_emails.domains' => ['mailinator.com', 'guerrillamail.com', 'yopmail.com']]);
    config(['disposable_emails.extra' => []]);
});

it('rejects a known disposable host', function (): void {
    $failed = false;
    (new NotDisposableEmail)->validate('email', 'foo@mailinator.com', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('rejects subdomains of a blocked host', function (): void {
    $failed = false;
    (new NotDisposableEmail)->validate('email', 'foo@bar.mailinator.com', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('does not reject a clean host', function (): void {
    $failed = false;
    (new NotDisposableEmail)->validate('email', 'foo@example.com', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('skips when feature is disabled in config', function (): void {
    config(['disposable_emails.enabled' => false]);

    $failed = false;
    (new NotDisposableEmail)->validate('email', 'foo@mailinator.com', function () use (&$failed) {
        $failed = true;
    });

    // The runtime guard only short-circuits when the rule's `isEnabled()`
    // method returns false. Without AuthenticationSettings in the container,
    // it falls back to the config flag.
    expect($failed)->toBeFalse();
});

it('honours the extra blocklist', function (): void {
    config(['disposable_emails.extra' => ['MyCorpBan.test']]);

    $failed = false;
    (new NotDisposableEmail)->validate('email', 'foo@mycorpban.test', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});
