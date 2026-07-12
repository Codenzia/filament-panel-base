<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Services;

use Codenzia\FilamentPanelBase\Analytics\Jobs\RecordVisitJob;
use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Builds a VisitData from the request/response/session and dispatches it to
 * the configured queue (or writes synchronously when `write_queue` is null).
 *
 * Lookups for the active panel + tenant are deferred to call time so this
 * service is safe to construct before Filament boots (e.g. in tests).
 */
class VisitWriter
{
    public function __construct(
        private readonly AnalyticsSettings $settings,
        private readonly BotDetector $botDetector,
        private readonly IpAnonymizer $ipAnonymizer,
        private readonly UserAgentParser $userAgentParser,
    ) {}

    public function record(Request $request, Response $response, ?float $startedAt = null): void
    {
        if (! $this->settings->enabled || ! $this->settings->track_visits) {
            return;
        }

        try {
            $data = $this->build($request, $response, $startedAt);

            // Bot rows are always stored (flagged via `is_bot`); the `bot_filter`
            // setting governs their exclusion from default widgets, which
            // `humans()` already enforces at query time.
            $this->dispatch($data);
        } catch (Throwable) {
            // Never break the request because of analytics. Failures swallowed.
        }
    }

    public function build(Request $request, Response $response, ?float $startedAt = null): VisitData
    {
        $userAgent = $request->userAgent();
        $isBot = $this->botDetector->isBot($userAgent);
        $parsedAgent = $this->userAgentParser->parse($userAgent);

        $data = new VisitData(
            sessionId: $request->hasSession() ? $request->session()->getId() : null,
            userId: Auth::id(),
            panel: $this->resolvePanelId(),
            routeName: $request->route()?->getName(),
            path: $this->trim($request->path(), 2048),
            method: substr(strtoupper($request->method()), 0, 8),
            status: $response->getStatusCode(),
            referrerHost: $this->referrerHost($request),
            countryCode: $this->resolveCountryCode(),
            ipHash: $this->ipAnonymizer->hash($request->ip()),
            deviceType: $parsedAgent['device'],
            browser: $parsedAgent['browser'],
            platform: $parsedAgent['platform'],
            isBot: $isBot,
            durationMs: $startedAt !== null ? (int) round((microtime(true) - $startedAt) * 1000) : null,
            createdAt: now()->toDateTimeString(),
        );

        [$data->tenantId, $data->tenantType] = $this->resolveTenant();

        return $data;
    }

    private function dispatch(VisitData $data): void
    {
        $queue = $this->settings->write_queue;

        if ($queue === null) {
            // Synchronous insert — no queue worker needed.
            Visit::create($data->toArray());

            return;
        }

        RecordVisitJob::dispatch($data)->onQueue($queue);
    }

    private function resolvePanelId(): ?string
    {
        if (! function_exists('filament')) {
            return null;
        }

        try {
            return filament()->getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{0: ?string, 1: ?string} */
    private function resolveTenant(): array
    {
        if (! function_exists('filament')) {
            return [null, null];
        }

        try {
            $tenant = filament()->getTenant();
        } catch (Throwable) {
            return [null, null];
        }

        if ($tenant === null) {
            return [null, null];
        }

        return [(string) $tenant->getKey(), $tenant::class];
    }

    private function resolveCountryCode(): ?string
    {
        // session('country_id') is set by SetCountry middleware; the country
        // model exposes a `code` accessor. We resolve at write time so the
        // recorded value is the country code (ISO 3166-1 alpha-2), not the id.
        if (! session()?->has('country_id')) {
            return null;
        }

        $model = config('filament-panel-base.country.model');
        $id = session('country_id');

        if (! $model || ! class_exists($model) || ! $id) {
            return null;
        }

        try {
            /** @var object|null $country */
            $country = $model::find($id);
            $code = $country?->code ?? null;

            return is_string($code) ? strtoupper(substr($code, 0, 2)) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function referrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) ? $this->trim($host, 255) : null;
    }

    private function trim(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
