<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Data;

use Illuminate\Support\Carbon;

/**
 * Read-only DTO representing one row from Laravel's `sessions` table,
 * enriched with a parsed device label and the "is this the current
 * request's session?" flag the UI uses to label the row "This device".
 */
readonly class DeviceSession
{
    public function __construct(
        public string $id,
        public ?int $userId,
        public string $ipAddress,
        public ?string $userAgent,
        public ?string $browser,
        public ?string $platform,
        public string $deviceType,
        public Carbon $lastActivity,
        public bool $isCurrent,
    ) {}

    public function label(): string
    {
        $browser = $this->browser ?: 'Unknown browser';
        $platform = $this->platform ?: 'Unknown OS';

        return $browser.' · '.$platform;
    }
}
