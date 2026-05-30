<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired immediately after a user confirms TOTP enrolment by submitting
 * a valid code from their authenticator app.
 */
class TwoFactorEnabled
{
    use Dispatchable;

    public function __construct(public Authenticatable $user) {}
}
