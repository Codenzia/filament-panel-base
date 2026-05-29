<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Services;

/**
 * Plain DTO for a single visit row. Built by VisitWriter from the request,
 * carried as the payload to RecordVisitJob.
 *
 * Kept as a plain class (not a readonly record) for trivial Serializable
 * compatibility with the queue payload, which Laravel JSON-encodes via
 * SerializesModels-friendly reflection.
 */
class VisitData
{
    public function __construct(
        public ?string $sessionId = null,
        public ?int $userId = null,
        public ?string $tenantId = null,
        public ?string $tenantType = null,
        public ?string $panel = null,
        public ?string $routeName = null,
        public string $path = '/',
        public string $method = 'GET',
        public int $status = 200,
        public ?string $referrerHost = null,
        public ?string $countryCode = null,
        public ?string $ipHash = null,
        public ?string $deviceType = null,
        public ?string $browser = null,
        public ?string $platform = null,
        public bool $isBot = false,
        public ?int $durationMs = null,
        public ?string $createdAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'tenant_type' => $this->tenantType,
            'panel' => $this->panel,
            'route_name' => $this->routeName,
            'path' => $this->path,
            'method' => $this->method,
            'status' => $this->status,
            'referrer_host' => $this->referrerHost,
            'country_code' => $this->countryCode,
            'ip_hash' => $this->ipHash,
            'device_type' => $this->deviceType,
            'browser' => $this->browser,
            'platform' => $this->platform,
            'is_bot' => $this->isBot,
            'duration_ms' => $this->durationMs,
            'created_at' => $this->createdAt,
        ];
    }
}
