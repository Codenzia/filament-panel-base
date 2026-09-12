<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Exceptions;

use RuntimeException;

/**
 * The two-factor policy could not be read for a user who is enrolled.
 *
 * Raised instead of quietly skipping the challenge: an unreachable settings
 * store is a service failure, and completing a sign-in that the host's policy
 * says must be challenged would silently downgrade the account to password
 * only, exactly when operators need the behaviour to be predictable.
 *
 * Callers turn this into a recoverable "try again shortly" message and leave
 * the user signed out.
 */
class TwoFactorUnavailableException extends RuntimeException {}
