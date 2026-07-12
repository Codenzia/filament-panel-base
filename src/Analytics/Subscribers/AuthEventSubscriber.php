<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Analytics\Subscribers;

use Codenzia\FilamentPanelBase\Analytics\Models\AuthEvent;
use Codenzia\FilamentPanelBase\Analytics\Services\IpAnonymizer;
use Codenzia\FilamentPanelBase\Analytics\Settings\AnalyticsSettings;
use Codenzia\FilamentPanelBase\Auth\Events\ModerationApproved;
use Codenzia\FilamentPanelBase\Auth\Events\ModerationPending;
use Codenzia\FilamentPanelBase\Auth\Events\ModerationSuspended;
use Codenzia\FilamentPanelBase\Auth\Events\OtpRequested;
use Codenzia\FilamentPanelBase\Auth\Events\OtpVerified;
use Codenzia\FilamentPanelBase\Auth\Events\SocialUserLinked;
use Codenzia\FilamentPanelBase\Auth\Events\UserRegistered;
use Codenzia\FilamentPanelBase\TwoFactor\Events\RecoveryCodeUsed;
use Codenzia\FilamentPanelBase\TwoFactor\Events\TwoFactorChallengeFailed;
use Codenzia\FilamentPanelBase\TwoFactor\Events\TwoFactorDisabled;
use Codenzia\FilamentPanelBase\TwoFactor\Events\TwoFactorEnabled;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Translates Laravel + package auth events into rows in the `auth_events`
 * table. Synchronous (low-volume vs page views), every handler wrapped in
 * try/catch so analytics failures never break the auth flow.
 *
 * Subscribed once from FilamentPanelBaseServiceProvider::bootAnalyticsModule()
 * via Event::subscribe(AuthEventSubscriber::class). Active only when
 * AnalyticsSettings::$enabled && $track_auth_events are both true.
 */
class AuthEventSubscriber
{
    public function __construct(
        private readonly AnalyticsSettings $settings,
        private readonly IpAnonymizer $ipAnonymizer,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'onLogin']);
        $events->listen(Failed::class, [self::class, 'onFailed']);
        $events->listen(Logout::class, [self::class, 'onLogout']);

        $events->listen(UserRegistered::class, [self::class, 'onRegistered']);
        $events->listen(OtpRequested::class, [self::class, 'onOtpRequested']);
        $events->listen(OtpVerified::class, [self::class, 'onOtpVerified']);
        $events->listen(SocialUserLinked::class, [self::class, 'onSocialLinked']);
        $events->listen(ModerationApproved::class, [self::class, 'onModerationApproved']);
        $events->listen(ModerationSuspended::class, [self::class, 'onModerationSuspended']);
        $events->listen(ModerationPending::class, [self::class, 'onModerationPending']);

        $events->listen(TwoFactorEnabled::class, [self::class, 'onTwoFactorEnabled']);
        $events->listen(TwoFactorDisabled::class, [self::class, 'onTwoFactorDisabled']);
        $events->listen(TwoFactorChallengeFailed::class, [self::class, 'onTwoFactorChallengeFailed']);
        $events->listen(RecoveryCodeUsed::class, [self::class, 'onTwoFactorRecoveryUsed']);
    }

    public function onLogin(Login $event): void
    {
        $this->record(AuthEvent::TYPE_LOGIN_SUCCESS, $event->user, [
            'guard' => $event->guard,
        ]);
    }

    public function onFailed(Failed $event): void
    {
        $this->record(AuthEvent::TYPE_LOGIN_FAILED, $event->user, [
            'guard' => $event->guard,
            'credentials_keys' => array_keys($event->credentials),
        ]);
    }

    public function onLogout(Logout $event): void
    {
        $this->record(AuthEvent::TYPE_LOGOUT, $event->user, [
            'guard' => $event->guard,
        ]);
    }

    public function onRegistered(UserRegistered $event): void
    {
        $this->record(AuthEvent::TYPE_REGISTER, $event->user, $event->context);
    }

    public function onOtpRequested(OtpRequested $event): void
    {
        $this->record(
            type: AuthEvent::TYPE_OTP_REQUESTED,
            user: null,
            meta: array_merge(
                ['target' => $this->maskTarget($event->target)],
                array_intersect_key($event->context, array_flip(['brand', 'locale'])),
            ),
            channel: $event->channel,
        );
    }

    public function onOtpVerified(OtpVerified $event): void
    {
        $this->record(
            type: AuthEvent::TYPE_OTP_VERIFIED,
            user: null,
            meta: ['target' => $this->maskTarget($event->target)],
            channel: $event->channel,
        );
    }

    /**
     * HMAC the OTP target (email/phone) before persisting so the analytics
     * table never stores raw PII while remaining correlatable for funnels.
     */
    private function maskTarget(string $target): string
    {
        return hash_hmac('sha256', $target, (string) config('app.key'));
    }

    public function onSocialLinked(SocialUserLinked $event): void
    {
        $this->record(AuthEvent::TYPE_SOCIAL_LOGIN, $event->user, [
            'provider' => $event->provider,
            'linked_first_time' => $event->linked,
        ]);
    }

    public function onModerationApproved(ModerationApproved $event): void
    {
        $this->record(AuthEvent::TYPE_MODERATION_APPROVED, $event->user, [
            'previous_status' => $event->previousStatus,
        ]);
    }

    public function onModerationSuspended(ModerationSuspended $event): void
    {
        $this->record(AuthEvent::TYPE_MODERATION_SUSPENDED, $event->user, [
            'previous_status' => $event->previousStatus,
            'reason' => $event->reason,
        ]);
    }

    public function onModerationPending(ModerationPending $event): void
    {
        $this->record(AuthEvent::TYPE_MODERATION_PENDING, $event->user);
    }

    public function onTwoFactorEnabled(TwoFactorEnabled $event): void
    {
        $this->record(AuthEvent::TYPE_TWO_FACTOR_ENABLED, $event->user);
    }

    public function onTwoFactorDisabled(TwoFactorDisabled $event): void
    {
        $this->record(AuthEvent::TYPE_TWO_FACTOR_DISABLED, $event->user);
    }

    public function onTwoFactorChallengeFailed(TwoFactorChallengeFailed $event): void
    {
        $this->record(AuthEvent::TYPE_TWO_FACTOR_FAILED, $event->user);
    }

    public function onTwoFactorRecoveryUsed(RecoveryCodeUsed $event): void
    {
        $this->record(AuthEvent::TYPE_TWO_FACTOR_RECOVERY_USED, $event->user);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function record(
        string $type,
        ?object $user = null,
        array $meta = [],
        ?string $channel = null,
    ): void {
        try {
            // Settings access can throw on fresh installs / test envs
            // where the `settings` table hasn't been migrated yet —
            // wrap the whole thing so analytics never breaks auth.
            if (! $this->settings->enabled || ! $this->settings->track_auth_events) {
                return;
            }

            AuthEvent::create([
                'user_id' => $this->resolveUserId($user),
                'tenant_id' => $this->resolveTenantId(),
                'tenant_type' => $this->resolveTenantType(),
                'panel' => $this->resolvePanelId(),
                'type' => $type,
                'channel' => $channel,
                'ip_hash' => $this->ipAnonymizer->hash(request()?->ip()),
                'country_code' => $this->resolveCountryCode(),
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never break auth because analytics insert failed.
        }
    }

    private function resolveUserId(?object $user): ?int
    {
        if ($user instanceof Model) {
            $key = $user->getKey();

            return is_numeric($key) ? (int) $key : null;
        }

        return null;
    }

    private function resolvePanelId(): ?string
    {
        if (! function_exists('filament')) {
            return null;
        }

        try {
            return filament()->getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveTenantId(): ?string
    {
        $tenant = $this->tenant();

        return $tenant ? (string) $tenant->getKey() : null;
    }

    private function resolveTenantType(): ?string
    {
        $tenant = $this->tenant();

        return $tenant ? $tenant::class : null;
    }

    private function tenant(): ?Model
    {
        if (! function_exists('filament')) {
            return null;
        }

        try {
            $tenant = filament()->getTenant();

            return $tenant instanceof Model ? $tenant : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveCountryCode(): ?string
    {
        if (! session()?->has('country_id')) {
            return null;
        }

        $model = config('filament-panel-base.country.model');
        $id = session('country_id');

        if (! $model || ! class_exists($model) || ! $id) {
            return null;
        }

        try {
            $country = $model::find($id);
            $code = $country?->code ?? null;

            return is_string($code) ? strtoupper(substr($code, 0, 2)) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
