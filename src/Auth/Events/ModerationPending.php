<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired immediately after a moderated user is persisted with status='pending'.
 * Hosts subscribe to send admin notifications, post to Slack, etc.
 */
class ModerationPending
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
    ) {}
}
