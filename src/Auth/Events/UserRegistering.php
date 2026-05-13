<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired *before* the user record is persisted. Listeners may modify the
 * payload by reference (e.g. attach Turnstile validation results) and may
 * cancel the registration by setting `$cancelled = true` with a reason.
 */
class UserRegistering
{
    use Dispatchable;

    public bool $cancelled = false;

    public ?string $cancellationReason = null;

    /**
     * @param  array<string, mixed>  $payload  Form data — referenced so listeners can mutate.
     * @param  array<string, mixed>  $context  Locale, IP, panel id, etc.
     */
    public function __construct(
        public array &$payload,
        public array $context = [],
    ) {}

    public function cancel(string $reason): void
    {
        $this->cancelled = true;
        $this->cancellationReason = $reason;
    }
}
