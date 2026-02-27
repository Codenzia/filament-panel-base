<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Shared registration settings for all Codenzia projects.
 *
 * Controls whether user registration is open or moderated,
 * and whether email verification is required.
 */
class RegistrationSettings extends Settings
{
    /** 'open' = immediate access, 'moderated' = admin approval required */
    public string $registration_mode = 'open';

    /** Require users to verify their email address before accessing the platform */
    public bool $require_email_verification = true;

    public static function group(): string
    {
        return 'registration';
    }
}
