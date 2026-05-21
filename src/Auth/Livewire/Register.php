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
        $fullPhone = $this->normalisePhone();

        // Identifier for throttle: prefer email, fall back to phone, fall back
        // to name. Burning budget on form spam is fine — register attempts
        // create persistent rows in the users table, so even valid floods are
        // worth rate-limiting before the validator runs.
        $identifier = $this->email !== '' ? $this->email : ($fullPhone ?? $this->name);
        $attribute = $this->email !== '' ? 'email' : ($fullPhone !== null ? 'phone' : 'name');

        $this->ensureNotRateLimited('register', $identifier, $attribute);
        $this->hitRateLimiter('register', $identifier);

        $this->validate(RegistrationRules::build($settings));

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

        // Phone OTP next, if the user has a phone and the setting requires it.
        if ($settings->require_phone_verification && filled($fullPhone) && $user instanceof HasPhone && ! $user->hasVerifiedPhone()) {
            $target = $user instanceof HasOtpVerification
                ? ($user->getOtpTarget($settings->otp_driver) ?? $fullPhone)
                : $fullPhone;

            $otp->send($target, $settings->otp_driver, context: [
                'brand' => config('app.name'),
                'ttl_minutes' => $settings->otp_ttl_minutes,
                'locale' => app()->getLocale(),
            ]);

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
            ->layout(config('filament-panel-base.auth.layout', 'filament-panel-base::layouts.auth'))
            ->title(__('filament-panel-base::auth.register_title'));
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
