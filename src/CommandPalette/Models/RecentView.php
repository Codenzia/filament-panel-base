<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\CommandPalette\Models;

use Illuminate\Database\Eloquent\Model;

class RecentView extends Model
{
    protected $table = 'command_palette_recent_views';

    protected $guarded = [];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];
}
