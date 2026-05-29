<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a user submits an invalid TOTP or recovery code at the
 * post-login challenge. The AnalyticsSubscriber listens and persists
 * this as an `auth_events` row (`type=two_factor.failed`) so admin
 * dashboards can spike on brute-force attempts.
 */
class TwoFactorChallengeFailed
{
    use Dispatchable;

    public function __construct(public Authenticatable $user) {}
}
