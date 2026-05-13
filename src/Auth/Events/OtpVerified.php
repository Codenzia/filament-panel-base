<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OtpVerified
{
    use Dispatchable;

    public function __construct(
        public readonly string $target,
        public readonly string $channel,
    ) {}
}
