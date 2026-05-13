<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OtpRequested
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $target,
        public readonly string $channel,
        public readonly array $context = [],
    ) {}
}
