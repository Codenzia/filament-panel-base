<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

class VisitDaily extends Model
{
    protected $table = 'visits_daily';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'views' => 'int',
        'unique_visitors' => 'int',
        'unique_sessions' => 'int',
        'bot_views' => 'int',
    ];

    public function scopeForPanel($query, ?string $panel)
    {
        return $panel === null ? $query : $query->where('panel', $panel);
    }
}
