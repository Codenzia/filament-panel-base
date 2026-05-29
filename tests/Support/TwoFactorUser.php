<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Tests\Support;

use Codenzia\FilamentPanelBase\TwoFactor\Concerns\HasTwoFactorAuthentication;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal Authenticatable for tests that exercise the 2FA trait against
 * a real model. The `users` table created by TestCase::createUsersTable()
 * already carries the three 2FA columns this trait expects.
 */
class TwoFactorUser extends Authenticatable
{
    use HasTwoFactorAuthentication;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'two_factor_confirmed_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];
}
