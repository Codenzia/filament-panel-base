<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when 2FA enrolment is fully removed (secret + recovery codes
 * cleared, confirmed_at nulled). Useful for compliance audit trails.
 */
class TwoFactorDisabled
{
    use Dispatchable;

    public function __construct(public Authenticatable $user) {}
}
