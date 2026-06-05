<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Services;

use Codenzia\FilamentPanelBase\Auth\Events\ModerationPending;
use Codenzia\FilamentPanelBase\Auth\Events\UserRegistered;
use Codenzia\FilamentPanelBase\Auth\Events\UserRegistering;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates user creation:
 *  1. Fire UserRegistering (cancellable — spam/Turnstile listeners can veto).
 *  2. Set the moderation status based on AuthenticationSettings.
 *  3. Create the user inside a DB transaction.
 *  4. Fire Laravel's Registered event (powers email verification).
 *  5. Fire UserRegistered + ModerationPending (if applicable).
 *
 * The resulting Authenticatable is returned to the caller, which is
 * responsible for whatever comes next (logging the user in, redirecting to
 * OTP verification, etc.).
 *
 * @throws \RuntimeException When a listener cancels the registration.
 */
class RegistrationPipeline
{
    public function __construct(
        private readonly AuthenticationSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @param  \Closure|null  $beforeUserCreation  Optional callback executed
     *        INSIDE the same DB transaction as user creation, just before
     *        $userModel::create() runs. Receives the payload array and may
     *        return a mutated payload array (or null to keep it unchanged).
     *
     *        This is the extension point downstream packages use to create
     *        related rows atomically with the user — e.g. tenant-module's
     *        TenantSignupService creates a Tenant here and adds `tenant_id`
     *        to the payload so the new user lands pre-scoped. If this closure
     *        throws, the user is never created.
     */
    public function register(
        string $userModel,
        array $payload,
        array $context = [],
        ?\Closure $beforeUserCreation = null,
    ): Authenticatable {
        $event = new UserRegistering($payload, $context);
        event($event);

        if ($event->cancelled) {
            throw new \RuntimeException($event->cancellationReason ?? __('Registration was cancelled.'));
        }

        $payload = $event->payload; // listeners may have mutated by reference

        $this->applyModerationStatus($payload);

        /** @var Authenticatable $user */
        $user = DB::transaction(function () use ($userModel, $payload, $beforeUserCreation) {
            if ($beforeUserCreation !== null) {
                $mutated = $beforeUserCreation($payload);

                if (is_array($mutated)) {
                    $payload = $mutated;
                }
            }

            /** @var Model $model */
            $model = $userModel::create($payload);

            return $model;
        });

        event(new Registered($user));
        event(new UserRegistered($user, $context));

        if ($user instanceof HasModerationStatus && $user->isPending()) {
            event(new ModerationPending($user));
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyModerationStatus(array &$payload): void
    {
        // Don't override callers that explicitly passed `status`.
        if (array_key_exists('status', $payload)) {
            return;
        }

        $payload['status'] = $this->settings->registration_mode === 'moderated' ? 'pending' : 'approved';
    }
}
