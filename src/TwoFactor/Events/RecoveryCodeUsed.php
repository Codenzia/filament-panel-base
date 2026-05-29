<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a single-use recovery code is consumed during the challenge.
 * Send the user a "we noticed you used a recovery code" email by hooking
 * this in a listener — that's how Github / GitLab / Fortify behave.
 */
class RecoveryCodeUsed
{
    use Dispatchable;

    public function __construct(public Authenticatable $user) {}
}
