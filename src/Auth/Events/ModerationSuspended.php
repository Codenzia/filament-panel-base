<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

class ModerationSuspended
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly ?string $previousStatus = null,
        public readonly ?string $reason = null,
    ) {}
}
