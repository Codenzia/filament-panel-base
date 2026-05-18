<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Tests\Support;

use Codenzia\FilamentPanelBase\Auth\Concerns\FindsOrCreatesFromSocialite;
use Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal Authenticatable used by the OAuth flow tests as the host User
 * model. Sets the table to `users` so the in-memory test schema lines up
 * with what the trait expects.
 */
class TestUser extends Authenticatable implements SupportsSocialLogin
{
    use FindsOrCreatesFromSocialite;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
