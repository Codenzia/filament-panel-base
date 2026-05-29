<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an Illuminate Login event corresponds to a (user, device
 * fingerprint) pair the database hasn't seen before. Hosts can listen
 * and notify the user — e.g. by sending a "new sign-in from Chrome on
 * macOS in Berlin" email.
 *
 * The fingerprint is intentionally coarse (IP + UA hash, not browser
 * cookies) so private-mode browsing on a known device doesn't trigger
 * false positives.
 */
class NewDeviceLogin
{
    use Dispatchable;

    public function __construct(
        public Authenticatable $user,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
