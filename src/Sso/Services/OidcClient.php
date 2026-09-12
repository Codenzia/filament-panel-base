<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Services;

use Codenzia\FilamentPanelBase\Sso\Exceptions\SsoException;
use Codenzia\FilamentPanelBase\Sso\Support\Base64Url;
use Codenzia\FilamentPanelBase\Sso\Support\ProviderConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * A lean OIDC relying-party client: discovery, the authorization-code request
 * (with PKCE) and the token exchange. Everything runs on Laravel's HTTP client
 * — no Socialite driver, no JOSE library.
 *
 * Nothing in here logs or returns a client secret, an authorization code, or
 * a token: failures raise an SsoException whose message is a translation key.
 */
class OidcClient
{
    private const HTTP_TIMEOUT = 10;

    /**
     * The provider's discovery document, cached.
     *
     * @return array<string, mixed>
     */
    public function discover(ProviderConfig $provider): array
    {
        $document = $this->remember(
            $this->cacheKey('discovery', $provider),
            fn (): array => $this->fetchJson($provider->discoveryUrl),
        );

        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $required) {
            if (! is_string($document[$required] ?? null) || $document[$required] === '') {
                throw SsoException::make(
                    'filament-panel-base::auth.sso_provider_error',
                    ['provider' => $provider->label],
                    "discovery document missing [{$required}]",
                );
            }
        }

        return $document;
    }

    /**
     * The provider's signing keys, cached.
     *
     * `$fresh` bypasses (and replaces) the cached copy. The caller uses it
     * exactly once per sign-in, after a token turned up signed by a key id the
     * cached set does not contain — otherwise an ordinary key rotation would
     * lock every user out until the TTL expired.
     *
     * @return array<int, array<string, mixed>>
     */
    public function jwks(ProviderConfig $provider, string $jwksUri, bool $fresh = false): array
    {
        $key = $this->cacheKey('jwks', $provider, $jwksUri);

        if ($fresh) {
            $this->forget($key);
        }

        $document = $this->remember(
            $key,
            fn (): array => $this->fetchJson($jwksUri),
        );

        $keys = $document['keys'] ?? null;

        if (! is_array($keys) || $keys === []) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $provider->label],
                'JWKS contained no keys',
            );
        }

        return array_values(array_filter($keys, 'is_array'));
    }

    /**
     * Build the authorization-endpoint URL the browser is sent to.
     *
     * @param  array<string, mixed>  $discovery
     */
    public function authorizationUrl(
        ProviderConfig $provider,
        array $discovery,
        string $redirectUri,
        string $state,
        string $nonce,
        string $codeVerifier,
    ): string {
        $query = [
            'client_id' => $provider->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $provider->scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Base64Url::encode(hash('sha256', $codeVerifier, true)),
            'code_challenge_method' => 'S256',
        ];

        $endpoint = (string) $discovery['authorization_endpoint'];
        $separator = str_contains($endpoint, '?') ? '&' : '?';

        return $endpoint.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Redeem the authorization code. Returns the raw token response; the
     * caller only ever reads `id_token` from it.
     *
     * @param  array<string, mixed>  $discovery
     * @return array<string, mixed>
     */
    public function exchangeCode(
        ProviderConfig $provider,
        array $discovery,
        string $code,
        string $redirectUri,
        string $codeVerifier,
    ): array {
        try {
            $response = Http::asForm()
                ->timeout(self::HTTP_TIMEOUT)
                ->withHeaders(['Accept' => 'application/json'])
                ->post((string) $discovery['token_endpoint'], [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $provider->clientId,
                    'client_secret' => $provider->clientSecret,
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (Throwable $e) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $provider->label],
                'token endpoint unreachable: '.$e::class,
            );
        }

        if ($response->failed()) {
            // Only the OAuth2 `error` code is carried across — never the body,
            // which can echo the submitted code or secret back at us.
            throw SsoException::make(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $provider->label],
                'token exchange failed: '.(is_string($response->json('error'))
                    ? $response->json('error')
                    : 'http '.$response->status()),
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_string($payload['id_token'] ?? null) || $payload['id_token'] === '') {
            throw SsoException::make(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $provider->label],
                'token response carried no id_token',
            );
        }

        return $payload;
    }

    /**
     * A high-entropy PKCE code verifier (RFC 7636 §4.1 — 43..128 chars from
     * the unreserved set).
     */
    public function generateCodeVerifier(): string
    {
        return Str::random(64);
    }

    /**
     * Cache keys carry a digest of the immutable provider configuration, so
     * re-pointing a provider entry at a different issuer or client id cannot
     * serve the previous IdP's discovery document or signing keys.
     */
    private function cacheKey(string $bucket, ProviderConfig $provider, string $extra = ''): string
    {
        $digest = substr(
            hash('sha256', $provider->discoveryUrl.'|'.$provider->clientId.'|'.$extra),
            0,
            32,
        );

        return "filament-panel-base.sso.{$bucket}.{$provider->key}.{$digest}";
    }

    private function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable) {
            // No usable cache store — nothing to invalidate.
        }
    }

    /**
     * @param  \Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function remember(string $key, \Closure $callback): array
    {
        $ttl = (int) config('filament-panel-base.sso.cache_ttl', 3600);

        if ($ttl <= 0) {
            return $callback();
        }

        try {
            /** @var array<string, mixed> $cached */
            $cached = Cache::remember($key, $ttl, $callback);

            return $cached;
        } catch (SsoException $e) {
            throw $e;
        } catch (Throwable) {
            // No usable cache store — fetch directly rather than failing the
            // sign-in over a caching problem.
            return $callback();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJson(string $url): array
    {
        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);
        } catch (Throwable $e) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_unavailable',
                [],
                'GET failed: '.$e::class,
            );
        }

        if ($response->failed()) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_unavailable',
                [],
                'GET returned http '.$response->status(),
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_unavailable',
                [],
                'response was not a JSON object',
            );
        }

        return $payload;
    }
}
