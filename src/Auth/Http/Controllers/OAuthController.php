<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Controllers;

use Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin;
use Codenzia\FilamentPanelBase\Auth\Services\SocialiteService;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Socialite redirect + callback. Providers are gated by both:
 *  - `services.{provider}.client_id` being configured (Laravel convention)
 *  - The provider name being in AuthenticationSettings::social_providers_enabled
 *
 * Hosts wire credentials in `config/services.php`; the admin only flips
 * the enable toggle from the settings UI.
 */
class OAuthController
{
    public function __construct(
        private readonly SocialiteService $socialite,
        private readonly AuthenticationSettings $settings,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $this->guard($provider);

        return $this->socialite->redirect($provider);
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->guard($provider);

        $userModel = config('filament-panel-base.user_model', \App\Models\User::class);

        if (! is_subclass_of($userModel, SupportsSocialLogin::class)) {
            throw new \LogicException(sprintf(
                '%s must implement %s to receive Socialite sign-ins.',
                $userModel,
                SupportsSocialLogin::class
            ));
        }

        $user = $this->socialite->handle($provider, $userModel);

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }

    private function guard(string $provider): void
    {
        if (! in_array($provider, $this->settings->social_providers_enabled, true)) {
            throw new NotFoundHttpException("Social provider [{$provider}] is not enabled.");
        }
    }
}
