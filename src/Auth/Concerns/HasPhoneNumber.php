<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Concerns;

use Codenzia\FilamentPanelBase\Auth\Contracts\HasPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Default implementation of {@see HasPhone}.
 *
 * Assumes the host's users table has nullable `phone` and `phone_verified_at`
 * columns. Use `php artisan filament-panel-base:install --auth` to publish
 * the migration that adds them when missing.
 *
 * @method static Builder verifiedPhone(Builder $query)
 */
trait HasPhoneNumber
{
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getPhoneVerifiedAt(): ?Carbon
    {
        $value = $this->phone_verified_at;

        return $value instanceof Carbon ? $value : (filled($value) ? Carbon::parse($value) : null);
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function markPhoneVerified(): bool
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function scopeVerifiedPhone(Builder $query): Builder
    {
        return $query->whereNotNull('phone_verified_at');
    }
}
