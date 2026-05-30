<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuthEvent extends Model
{
    public const TYPE_LOGIN_SUCCESS = 'login.success';

    public const TYPE_LOGIN_FAILED = 'login.failed';

    public const TYPE_LOGOUT = 'logout';

    public const TYPE_REGISTER = 'register';

    public const TYPE_OTP_REQUESTED = 'otp.requested';

    public const TYPE_OTP_VERIFIED = 'otp.verified';

    public const TYPE_SOCIAL_LOGIN = 'social.login';

    public const TYPE_MODERATION_SUSPENDED = 'moderation.suspended';

    public const TYPE_MODERATION_APPROVED = 'moderation.approved';

    public const TYPE_MODERATION_PENDING = 'moderation.pending';

    public const TYPE_PASSWORD_RESET = 'password.reset';

    public const TYPE_TWO_FACTOR_ENABLED = 'two_factor.enabled';

    public const TYPE_TWO_FACTOR_DISABLED = 'two_factor.disabled';

    public const TYPE_TWO_FACTOR_FAILED = 'two_factor.failed';

    public const TYPE_TWO_FACTOR_RECOVERY_USED = 'two_factor.recovery_used';

    protected $table = 'auth_events';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForPanel($query, ?string $panel)
    {
        return $panel === null ? $query : $query->where('panel', $panel);
    }
}
