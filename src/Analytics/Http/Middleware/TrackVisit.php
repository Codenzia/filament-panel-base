<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Http\Middleware;

use Closure;
use Codenzia\FilamentPanelBase\Analytics\Services\VisitWriter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a single `visits` row per request. Runs in `terminate()` so the
 * write (or queue dispatch) never sits in the response critical path.
 *
 * Skipped for: console requests, AJAX/JSON without a Livewire signature,
 * routes explicitly excluded via config('filament-panel-base.analytics.exclude_routes').
 */
class TrackVisit
{
    private ?float $startedAt = null;

    public function __construct(private readonly VisitWriter $writer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->startedAt = microtime(true);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldSkip($request)) {
            return;
        }

        $this->writer->record($request, $response, $this->startedAt);
    }

    private function shouldSkip(Request $request): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        // Skip non-GET writes unless they have a Livewire header — Livewire
        // updates are POSTs but represent real user interactions worth recording.
        $isLivewire = $request->headers->has('X-Livewire')
            || str_contains((string) $request->path(), 'livewire/update');

        if ($request->isMethod('GET') === false && ! $isLivewire) {
            return true;
        }

        // Headless JSON API calls (not Livewire) are usually not interesting
        // for visit analytics — they're machine traffic.
        if ($request->expectsJson() && ! $isLivewire) {
            return true;
        }

        $excluded = (array) config('filament-panel-base.analytics.exclude_routes', [
            'livewire/livewire.js',
            'livewire/livewire.js.map',
            'horizon*',
            'telescope*',
            'sanctum/*',
            '_debugbar/*',
        ]);

        foreach ($excluded as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
