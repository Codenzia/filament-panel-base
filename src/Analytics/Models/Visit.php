<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Visit extends Model
{
    protected $table = 'visits';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_bot' => 'bool',
        'status' => 'int',
        'duration_ms' => 'int',
        'created_at' => 'datetime',
    ];

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForPanel($query, ?string $panel)
    {
        return $panel === null ? $query : $query->where('panel', $panel);
    }

    public function scopeHumans($query)
    {
        return $query->where('is_bot', false);
    }
}
