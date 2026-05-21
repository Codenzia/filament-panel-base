<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One linked OAuth identity. A host User model can have many of these — one
 * row per provider the user has connected (google, github, apple, ...).
 *
 * Token columns are encrypted via Laravel's `encrypted` cast so a DB dump
 * does not leak live OAuth tokens. Hosts can read the decrypted values
 * directly via the model accessors.
 */
class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
        'name',
        'avatar',
        'token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('filament-panel-base.user_model', User::class),
            'user_id'
        );
    }

    /**
     * Whether the stored access token has expired (when expiry is known).
     */
    protected function isExpired(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->expires_at !== null && $this->expires_at->isPast()
        );
    }
}
