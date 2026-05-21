<?php

use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\EmailOtpDriver;
use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\NullOtpDriver;
use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\OtpDriver;
use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\TwilioSmsOtpDriver;
use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\VonageSmsOtpDriver;
use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\WhatsAppMetaOtpDriver;

it('null driver implements OtpDriver and returns its channel name', function (): void {
    $driver = new NullOtpDriver;
    expect($driver)->toBeInstanceOf(OtpDriver::class)
        ->and($driver->channel())->toBe('null');

    // Sanity check — should not throw (logs only).
    $driver->send('+962501234567', '123456');
    expect(true)->toBeTrue();
});

it('email driver returns its channel name', function (): void {
    $driver = new EmailOtpDriver;
    expect($driver)->toBeInstanceOf(OtpDriver::class)
        ->and($driver->channel())->toBe('email');
});

it('whatsapp meta driver returns its channel name', function (): void {
    $driver = new WhatsAppMetaOtpDriver('https://graph.facebook.com/v21.0', '', '', 'verification_code', 'en');
    expect($driver)->toBeInstanceOf(OtpDriver::class)
        ->and($driver->channel())->toBe('whatsapp');
});

it('twilio driver returns its channel name', function (): void {
    $driver = new TwilioSmsOtpDriver('', '', '');
    expect($driver)->toBeInstanceOf(OtpDriver::class)
        ->and($driver->channel())->toBe('twilio');
});

it('vonage driver returns its channel name', function (): void {
    $driver = new VonageSmsOtpDriver('', '', 'Codenzia');
    expect($driver)->toBeInstanceOf(OtpDriver::class)
        ->and($driver->channel())->toBe('vonage');
});

it('whatsapp driver suppresses send when credentials are missing', function (): void {
    $driver = new WhatsAppMetaOtpDriver('https://graph.facebook.com/v21.0', '', '', 'verification_code', 'en');

    expect(fn () => $driver->send('+962501234567', '123456'))->not->toThrow(Throwable::class);
});

it('twilio driver suppresses send when credentials are missing', function (): void {
    $driver = new TwilioSmsOtpDriver('', '', '');

    expect(fn () => $driver->send('+962501234567', '123456'))->not->toThrow(Throwable::class);
});

it('vonage driver suppresses send when credentials are missing', function (): void {
    $driver = new VonageSmsOtpDriver('', '', 'Codenzia');

    expect(fn () => $driver->send('+962501234567', '123456'))->not->toThrow(Throwable::class);
});
