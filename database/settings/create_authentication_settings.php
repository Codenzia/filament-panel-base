<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = [
            'auth.registration_mode' => 'open',
            'auth.require_email_verification' => true,
            'auth.require_phone_verification' => false,
            'auth.credentials_mode' => 'email',
            'auth.phone_required' => false,
            'auth.otp_driver' => 'email',
            'auth.allowed_otp_drivers' => ['email', 'whatsapp', 'twilio', 'vonage', 'null'],
            'auth.social_providers_enabled' => [],
            'auth.disposable_email_blocking' => true,
            'auth.throttle_per_minute' => 5,
            'auth.throttle_per_day' => 50,
            'auth.default_country_code' => '+1',
            'auth.otp_code_length' => 6,
            'auth.otp_ttl_minutes' => 10,
        ];

        foreach ($defaults as $key => $value) {
            if (! $this->migrator->exists($key)) {
                $this->migrator->add($key, $value);
            }
        }
    }
};
