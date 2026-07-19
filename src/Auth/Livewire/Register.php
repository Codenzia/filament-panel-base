<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Livewire;

use App\Models\User;
use Codenzia\FilamentPanelBase\Auth\Concerns\ThrottlesAuthAttempts;
use Codenzia\FilamentPanelBase\Auth\Contracts\HasOtpVerification;
use Codenzia\FilamentPanelBase\Auth\Contracts\HasPhone;
use Codenzia\FilamentPanelBase\Auth\Services\OtpService;
use Codenzia\FilamentPanelBase\Auth\Services\RegistrationPipeline;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Auth\Validation\RegistrationRules;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Front-of-site Livewire register page. Adapts its form to
 * AuthenticationSettings::credentials_mode at runtime so the same
 * component covers email-only, phone-only, and email+phone signups.
 */
class Register extends Component
{
    use ThrottlesAuthAttempts;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $phone = '';

    public string $country_code = '';

    public function mount(AuthenticationSettings $settings): void
    {
        $this->country_code = $settings->default_country_code;
    }

    public function register(AuthenticationSettings $settings, RegistrationPipeline $pipeline, OtpService $otp): void
    {
        $this->email = $this->normaliseEmail();
        $fullPhone = $this->normalisePhone();

        // Identifier for throttle: prefer email, fall back to phone, fall back
        // to name.
        $identifier = $this->email !== '' ? $this->email : ($fullPhone ?? $this->name);
        $attribute = $this->email !== '' ? 'email' : ($fullPhone !== null ? 'phone' : 'name');

        $this->ensureNotRateLimited('register', $identifier, $attribute);

        $this->validate(RegistrationRules::build($settings));

        // Only spend the rate-limit budget on submissions that actually clear
        // validation, so a stream of malformed attempts can't lock the bucket
        // for a legitimate signup (PNB-037).
        $this->hitRateLimiter('register', $identifier);

        $userModel = config('filament-panel-base.user_model', User::class);

        $payload = array_filter([
            'name' => $this->name,
            'email' => $this->email !== '' ? $this->email : null,
            'password' => $this->password,
            'phone' => $fullPhone,
        ], static fn ($value) => $value !== null);

        $user = $pipeline->register($userModel, $payload, context: [
            'source' => 'livewire-register',
            'locale' => app()->getLocale(),
            'ip' => request()->ip(),
        ]);

        Auth::login($user);
        session()->regenerate();

        // Phone OTP next, if the user has a phone and the setting requires it.
        if ($settings->require_phone_verification && filled($fullPhone) && $user instanceof HasPhone && ! $user->hasVerifiedPhone()) {
            $target = $user instanceof HasOtpVerification
                ? ($user->getOtpTarget($settings->otp_driver) ?? $fullPhone)
                : $fullPhone;

            try {
                $otp->send($target, $settings->otp_driver, context: [
                    'brand' => config('app.name'),
                    'ttl_minutes' => $settings->otp_ttl_minutes,
                    'locale' => app()->getLocale(),
                ], userId: $user->getKey());
            } catch (\RuntimeException $exception) {
                // The account already exists and the verify page has a Resend
                // button — surface the delivery/rate-limit error there rather
                // than bubbling a 500.
                session()->flash('status', $exception->getMessage());
            }

            $this->redirect(route('verification.otp'), navigate: true);

            return;
        }

        // Email verification (Laravel built-in) — only when the host's User
        // implements MustVerifyEmail and the setting requires it.
        if ($settings->require_email_verification && method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
            $this->redirect(route('verification.notice'), navigate: true);

            return;
        }

        // Pending users land on home — middleware will redirect them away from
        // gated routes with a friendly notice.
        if ($user instanceof HasModerationStatus && $user->isPending()) {
            $this->redirect(route('home'), navigate: true);

            return;
        }

        $this->redirect(route('home'), navigate: true);
    }

    public function render(AuthenticationSettings $settings): View
    {
        return view('filament-panel-base::livewire.auth.register', [
            'credentialsMode' => $settings->credentials_mode,
            'phoneRequired' => $settings->phone_required || $settings->credentials_mode === 'phone',
            'enabledSocialProviders' => $settings->social_providers_enabled,
        ])
            ->layout(config('filament-panel-base.auth.layout') ?: 'filament-panel-base::layouts.auth')
            ->title(__('filament-panel-base::auth.register_title'));
    }

    /**
     * Lower-case and trim the email once at intake so a case/whitespace
     * variant can't create a second account (and so the DB unique check and
     * later login lookups line up on case-sensitive stores like PostgreSQL).
     */
    private function normaliseEmail(): string
    {
        return $this->email === '' ? '' : mb_strtolower(trim($this->email));
    }

    /**
     * Combine country_code + phone into E.164. Returns null when no phone
     * was provided so the rule set's `nullable` branch can apply.
     */
    private function normalisePhone(): ?string
    {
        if ($this->phone === '') {
            return null;
        }

        $full = str_starts_with($this->phone, '+') ? $this->phone : ($this->country_code.$this->phone);
        $this->phone = (string) $full;

        return $full;
    }
}
