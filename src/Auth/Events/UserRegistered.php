<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a new user has been persisted by the registration pipeline.
 * Distinct from Laravel's built-in `Illuminate\Auth\Events\Registered` —
 * this one carries the registration context (channel, panel, etc.) so
 * listeners can branch on it.
 */
class UserRegistered
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Authenticatable $user,
        public readonly array $context = [],
    ) {}
}
