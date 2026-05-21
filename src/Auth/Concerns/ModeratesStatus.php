<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Concerns;

use Codenzia\FilamentPanelBase\Auth\Events\ModerationApproved;
use Codenzia\FilamentPanelBase\Auth\Events\ModerationSuspended;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Default implementation of the status helpers required by
 * {@see HasModerationStatus}.
 *
 * Assumes the host's users table has a string `status` column with the
 * canonical values 'pending', 'approved', 'suspended'.
 *
 * @method static Builder approved(Builder $query)
 * @method static Builder pending(Builder $query)
 * @method static Builder suspended(Builder $query)
 */
trait ModeratesStatus
{
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Transition the user to 'approved' and fire the {@see ModerationApproved} event.
     */
    public function approve(): bool
    {
        $previous = $this->status;
        $saved = $this->forceFill(['status' => 'approved'])->save();

        if ($saved && $previous !== 'approved') {
            event(new ModerationApproved($this, $previous));
        }

        return $saved;
    }

    /**
     * Transition the user to 'suspended' and fire the {@see ModerationSuspended} event.
     */
    public function suspend(?string $reason = null): bool
    {
        $previous = $this->status;
        $saved = $this->forceFill(['status' => 'suspended'])->save();

        if ($saved && $previous !== 'suspended') {
            event(new ModerationSuspended($this, $previous, $reason));
        }

        return $saved;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }
}
