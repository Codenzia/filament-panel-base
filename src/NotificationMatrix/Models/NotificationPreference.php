<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\NotificationMatrix\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single opt-out/opt-in row: one (user, trigger, channel) tuple that
 * diverges from the trigger's registered default. Absence of a row means
 * "use the trigger's default_enabled" — see NotificationPreferences::allows().
 *
 * @property int $id
 * @property int $user_id
 * @property string $user_type
 * @property string $trigger_key
 * @property string $channel
 * @property bool $enabled
 */
class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
