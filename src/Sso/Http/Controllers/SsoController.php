<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Http\Controllers;

use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Codenzia\FilamentPanelBase\Sso\Exceptions\SsoException;
use Codenzia\FilamentPanelBase\Sso\ProviderRegistry;
use Codenzia\FilamentPanelBase\Sso\Services\IdTokenVerifier;
use Codenzia\FilamentPanelBase\Sso\Services\OidcClient;
use Codenzia\FilamentPanelBase\Sso\Services\SsoAuthenticator;
use Codenzia\FilamentPanelBase\Sso\Support\ProviderConfig;
use Codenzia\FilamentPanelBase\Support\SessionExpiry;
use Codenzia\FilamentPanelBase\TwoFactor\Exceptions\TwoFactorUnavailableException;
use Codenzia\FilamentPanelBase\TwoFactor\Services\LoginChallengeDecider;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The OIDC authorization-code flow, with PKCE.
 *
 * `redirect()` mints the three one-time values the flow rests on — `state`
 * (CSRF), `nonce` (token replay) and the PKCE verifier (code interception) —
 * stashes them in the session, and sends the browser to the provider.
 * `callback()` re-checks all three before a claim is trusted.
 *
 * The stashed flow is PULLED, not read, so a callback URL is single-use: a
 * replayed callback finds no flow and is rejected.
 *
 * Failures never surface protocol detail to the browser. The user sees a
 * translated sentence; the technical reason goes to the log, and tokens,
 * codes and secrets go to neither.
 */
class SsoController
{
    private const FLOW_KEY = 'filament-panel-base.sso.flow';

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly OidcClient $client,
        private readonly IdTokenVerifier $verifier,
        private readonly SsoAuthenticator $authenticator,
    ) {}

    public function redirect(string $provider, Request $request): RedirectResponse
    {
        $config = $this->providerOrFail($provider);

        try {
            $discovery = $this->client->discover($config);

            $state = Str::random(40);
            $nonce = Str::random(40);
            $codeVerifier = $this->client->generateCodeVerifier();

            $request->session()->put(self::FLOW_KEY, [
                'provider' => $config->key,
                'state' => $state,
                'nonce' => $nonce,
                'code_verifier' => $codeVerifier,
            ]);

            return redirect()->away($this->client->authorizationUrl(
                provider: $config,
                discovery: $discovery,
                redirectUri: $this->callbackUrl($config),
                state: $state,
                nonce: $nonce,
                codeVerifier: $codeVerifier,
            ));
        } catch (SsoException $e) {
            return $this->fail($e, $config);
        }
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $config = $this->providerOrFail($provider);

        // Single-use: pulled before anything else so neither a success nor a
        // failure can leave a replayable flow behind in the session.
        $flow = $request->session()->pull(self::FLOW_KEY);

        try {
            $this->assertState($flow, $config, $request);

            $user = $this->authenticate($config, $request, is_array($flow) ? $flow : []);
        } catch (SsoException $e) {
            return $this->fail($e, $config);
        }

        if ($user instanceof HasModerationStatus) {
            if ($user->isSuspended()) {
                return $this->flashAndReturn(__('filament-panel-base::auth.account_suspended'));
            }

            if ($user->isPending()) {
                return $this->flashAndReturn(__('filament-panel-base::auth.account_pending'));
            }
        }

        $remember = (bool) config('filament-panel-base.sso.remember', false);

        // A provider-asserted identity is not a second factor: an account with
        // 2FA enabled is challenged here exactly as on the password path.
        try {
            $challenge = app(LoginChallengeDecider::class)->shouldChallenge($user);
        } catch (TwoFactorUnavailableException) {
            return $this->flashAndReturn(__('filament-panel-base::two-factor.service_unavailable'));
        }

        if ($challenge) {
            // A required challenge with nowhere to send the user is a policy
            // failure, not permission to skip the second factor.
            if (! Route::has('two-factor.challenge')) {
                Log::warning('SSO sign-in requires a two-factor challenge but no two-factor.challenge route is registered');

                return $this->flashAndReturn(__('filament-panel-base::two-factor.service_unavailable'));
            }

            app(TwoFactorChallengeSession::class)->stash($user, $remember);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Exchange the code and turn the verified claims into a local user.
     *
     * @param  array<string, mixed>  $flow
     */
    private function authenticate(
        ProviderConfig $config,
        Request $request,
        array $flow,
    ): Model {
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            throw SsoException::make(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $config->label],
                'callback carried no authorization code',
            );
        }

        $discovery = $this->client->discover($config);

        $tokens = $this->client->exchangeCode(
            provider: $config,
            discovery: $discovery,
            code: $code,
            redirectUri: $this->callbackUrl($config),
            codeVerifier: (string) ($flow['code_verifier'] ?? ''),
        );

        $verify = fn (bool $freshKeys): array => $this->verifier->verify(
            idToken: (string) $tokens['id_token'],
            provider: $config,
            issuer: (string) $discovery['issuer'],
            jwks: $this->client->jwks($config, (string) $discovery['jwks_uri'], $freshKeys),
            expectedNonce: (string) ($flow['nonce'] ?? ''),
        );

        try {
            $claims = $verify(false);
        } catch (SsoException $e) {
            // A cached key set that predates a rotation is indistinguishable
            // from a forged token. Refetch the JWKS once — and only for that
            // one reason — before rejecting the sign-in.
            if (! $e->signingKeyUnknown) {
                throw $e;
            }

            $claims = $verify(true);
        }

        return $this->authenticator->resolve($config, $claims);
    }

    /**
     * Reject anything that is not this browser's own in-flight request, and
     * surface a provider-side error only after that has been established.
     */
    private function assertState(mixed $flow, ProviderConfig $config, Request $request): void
    {
        $state = $request->query('state');
        $expected = is_array($flow) ? (string) ($flow['state'] ?? '') : '';

        $matches = is_array($flow)
            && ($flow['provider'] ?? null) === $config->key
            && $expected !== ''
            && is_string($state)
            && hash_equals($expected, $state);

        if (! $matches) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_invalid_state',
                [],
                'state parameter did not match the stored flow',
            );
        }

        $error = $request->query('error');

        if (is_string($error) && $error !== '') {
            throw SsoException::make(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $config->label],
                // The OAuth2 error CODE only — `error_description` is provider
                // controlled text and is deliberately not echoed anywhere.
                'provider returned error: '.$error,
            );
        }
    }

    private function providerOrFail(string $provider): ProviderConfig
    {
        $config = $this->registry->find($provider);

        if (! $config instanceof ProviderConfig) {
            throw new NotFoundHttpException("SSO provider [{$provider}] is not enabled.");
        }

        return $config;
    }

    private function callbackUrl(ProviderConfig $config): string
    {
        return route('filament-panel-base.sso.callback', ['provider' => $config->key]);
    }

    private function fail(SsoException $e, ProviderConfig $config): RedirectResponse
    {
        Log::warning('SSO sign-in failed', [
            'provider' => $config->key,
            'reason' => $e->reason,
        ]);

        return $this->flashAndReturn($e->userMessage());
    }

    private function flashAndReturn(string $message): RedirectResponse
    {
        session()->flash('error', $message);

        return redirect()->to(SessionExpiry::redirectUrl());
    }
}
