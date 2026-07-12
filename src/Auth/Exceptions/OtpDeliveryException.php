<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Exceptions;

use RuntimeException;

/**
 * Thrown when an OTP transport fails to deliver a code (HTTP error, missing
 * credentials in a non-local environment, etc.). Callers surface this as a
 * user-facing delivery error instead of silently landing the user on a verify
 * page for a code that never arrived.
 */
class OtpDeliveryException extends RuntimeException {}
