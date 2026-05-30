<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Jobs;

use Codenzia\FilamentPanelBase\Analytics\Models\Visit;
use Codenzia\FilamentPanelBase\Analytics\Services\VisitData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordVisitJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $backoff = 60;

    public function __construct(public readonly VisitData $visit) {}

    public function handle(): void
    {
        Visit::create($this->visit->toArray());
    }
}
