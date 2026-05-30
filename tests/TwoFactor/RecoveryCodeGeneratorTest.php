<?php

use Codenzia\FilamentPanelBase\TwoFactor\Services\RecoveryCodeGenerator;

it('generates the requested number of codes', function (): void {
    $gen = new RecoveryCodeGenerator;

    expect($gen->generate(8))->toHaveCount(8);
    expect($gen->generate(1))->toHaveCount(1);
});

it('clamps non-positive counts to at least 1', function (): void {
    $gen = new RecoveryCodeGenerator;

    expect($gen->generate(0))->toHaveCount(1);
    expect($gen->generate(-5))->toHaveCount(1);
});

it('generates codes in the expected dashed shape', function (): void {
    $gen = new RecoveryCodeGenerator;
    $codes = $gen->generate(3);

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/');
    }
});

it('produces unique codes', function (): void {
    $gen = new RecoveryCodeGenerator;
    $codes = $gen->generate(20);

    expect(array_unique($codes))->toHaveCount(20);
});
