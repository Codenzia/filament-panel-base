<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Drivers\Otp;

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Manager;

/**
 * Resolves the active OTP driver. The default driver is read from
 * AuthenticationSettings (DB), with the config file as a fallback for fresh
 * installs where settings haven't been seeded yet.
 *
 * Hosts can register custom drivers with `Otp::extend('name', fn () => ...)`
 * or by overriding one of the createXxxDriver methods on this manager.
 */
class OtpDriverManager extends Manager
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function getDefaultDriver(): string
    {
        try {
            return $this->container->make(AuthenticationSettings::class)->otp_driver;
        } catch (\Throwable) {
            return (string) $this->container['config']->get('filament-panel-base.auth.otp.default', 'email');
        }
    }

    protected function createEmailDriver(): EmailOtpDriver
    {
        return new EmailOtpDriver;
    }

    protected function createWhatsappDriver(): WhatsAppMetaOtpDriver
    {
        $config = (array) $this->container['config']->get('filament-panel-base.auth.drivers.whatsapp', []);

        return new WhatsAppMetaOtpDriver(
            apiUrl: (string) ($config['api_url'] ?? 'https://graph.facebook.com/v21.0'),
            phoneId: (string) ($config['phone_id'] ?? ''),
            accessToken: (string) ($config['access_token'] ?? ''),
            templateName: (string) ($config['template_name'] ?? 'verification_code'),
            templateLanguage: (string) ($config['template_language'] ?? 'en'),
        );
    }

    protected function createTwilioDriver(): TwilioSmsOtpDriver
    {
        $config = (array) $this->container['config']->get('filament-panel-base.auth.drivers.twilio', []);

        return new TwilioSmsOtpDriver(
            sid: (string) ($config['sid'] ?? ''),
            token: (string) ($config['token'] ?? ''),
            from: (string) ($config['from'] ?? ''),
        );
    }

    protected function createVonageDriver(): VonageSmsOtpDriver
    {
        $config = (array) $this->container['config']->get('filament-panel-base.auth.drivers.vonage', []);

        return new VonageSmsOtpDriver(
            key: (string) ($config['key'] ?? ''),
            secret: (string) ($config['secret'] ?? ''),
            from: (string) ($config['sms_from'] ?? 'Codenzia'),
        );
    }

    protected function createNullDriver(): NullOtpDriver
    {
        return new NullOtpDriver;
    }
}
