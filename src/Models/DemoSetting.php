<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row holding the runtime demo-page password.
 *
 * Row id=1 is the only row used. The {@see static::current()} accessor
 * returns it (creating a blank row on first access).
 */
class DemoSetting extends Model
{
    protected $table = 'demo_settings';

    protected $fillable = [
        'password',
        'rotated_at',
        'last_used_at',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'rotated_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
