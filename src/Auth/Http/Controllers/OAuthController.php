<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Controllers;

use App\Models\User;
use Codenzia\FilamentPanelBase\Auth\Contracts\SupportsSocialLogin;
use Codenzia\FilamentPanelBase\Auth\Services\SocialiteService;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\InvalidStateException;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Socialite redirect + callback. Providers are gated by both:
 *  - `services.{provider}.client_id` being configured (Laravel convention)
 *  - The provider name being in AuthenticationSettings::social_providers_enabled
 *
 * Two flows pass through `callback()`:
 *  1. Sign-in / sign-up — anonymous visitor; hands off to the User model's
 *     `findOrCreateFromSocialite`.
 *  2. Connect-from-profile — authenticated user clicked "Connect Google" on
 *     the manage page. `redirect()` stored a session flag; callback attaches
 *     the new SocialAccount to the current user and returns to /profile.
 *
 * Hosts wire credentials in `config/services.php`; the admin only flips
 * the enable toggle from the settings UI.
 */
class OAuthController
{
    private const LINK_SESSION_KEY = 'filament-panel-base.oauth.link_flow';

    public function __construct(
        private readonly SocialiteService $socialite,
        private readonly AuthenticationSettings $settings,
    ) {}

    public function redirect(string $provider, Request $request): RedirectResponse
    {
        $this->guard($provider);

        if ($request->boolean('link') && Auth::check()) {
            $request->session()->put(self::LINK_SESSION_KEY, [
                'provider' => $provider,
                'user_id' => Auth::id(),
                'return_to' => $request->query('return_to', url()->previous()),
            ]);
        }

        return $this->socialite->redirect($provider);
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $this->guard($provider);

        $linkFlow = $request->session()->pull(self::LINK_SESSION_KEY);
        $isLinkFlow = is_array($linkFlow)
            && ($linkFlow['provider'] ?? null) === $provider
            && Auth::check()
            && (int) ($linkFlow['user_id'] ?? 0) === (int) Auth::id();

        $userModel = config('filament-panel-base.user_model', User::class);

        if (! is_subclass_of($userModel, SupportsSocialLogin::class)) {
            throw new LogicException(sprintf(
                '%s must implement %s to receive Socialite sign-ins.',
                $userModel,
                SupportsSocialLogin::class
            ));
        }

        try {
            $socialUser = $this->socialite->userFromCallback($provider);
        } catch (InvalidStateException) {
            return $this->redirectWithError(
                'filament-panel-base::auth.oauth_invalid_state',
                $isLinkFlow ? ($linkFlow['return_to'] ?? null) : null
            );
        } catch (Throwable $e) {
            Log::warning('OAuth callback failed', [
                'provider' => $provider,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->redirectWithError(
                'filament-panel-base::auth.oauth_provider_error',
                $isLinkFlow ? ($linkFlow['return_to'] ?? null) : null,
                ['provider' => ucfirst($provider)]
            );
        }

        if ($isLinkFlow) {
            $user = Auth::user();

            if (! $user instanceof SupportsSocialLogin) {
                throw new LogicException(sprintf(
                    'Authenticated user must implement %s to link a social account.',
                    SupportsSocialLogin::class
                ));
            }

            try {
                $user->linkSocialAccount($provider, $socialUser);
            } catch (RuntimeException $e) {
                session()->flash('error', $e->getMessage());

                return redirect()->to($linkFlow['return_to'] ?? '/');
            }

            session()->flash(
                'status',
                __('filament-panel-base::auth.social_link_success', [
                    'provider' => ucfirst($provider),
                ])
            );

            return redirect()->to($linkFlow['return_to'] ?? '/');
        }

        $user = $this->socialite->handle($provider, $userModel);

        if ($user === null) {
            return redirect()->route('login');
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }

    private function guard(string $provider): void
    {
        if (! in_array($provider, $this->settings->social_providers_enabled, true)) {
            throw new NotFoundHttpException("Social provider [{$provider}] is not enabled.");
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function redirectWithError(string $messageKey, ?string $returnTo, array $params = []): RedirectResponse
    {
        session()->flash('error', __($messageKey, $params));

        return $returnTo !== null
            ? redirect()->to($returnTo)
            : redirect()->route('login');
    }
}
