<?php

use Codenzia\FilamentPanelBase\Auth\Rules\ValidPhoneFormat;

it('accepts a well-formed E.164 number with the regex fallback', function (): void {
    // libphonenumber path is exercised only when the package is installed; the
    // regex fallback covers the unit test without depending on it.
    $failed = false;
    (new ValidPhoneFormat)->validate('phone', '+14155552671', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('rejects non-E.164 input via the regex fallback', function (): void {
    $cases = [
        '4155552671',    // missing leading +
        '+14',           // too short
        '+abcdefghij',   // non-numeric
        'twelve',        // not a number at all
    ];

    foreach ($cases as $candidate) {
        $failed = false;

        if (! class_exists(\Propaganistas\LaravelPhone\PhoneNumber::class)) {
            (new ValidPhoneFormat)->validate('phone', $candidate, function () use (&$failed) {
                $failed = true;
            });

            expect($failed)->toBeTrue("expected rejection for {$candidate}");
        }
    }
});

it('skips empty values (let `required` own that)', function (): void {
    $failed = false;
    (new ValidPhoneFormat)->validate('phone', '', function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('rejects non-string values', function (): void {
    $failed = false;
    (new ValidPhoneFormat)->validate('phone', 12345, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});
