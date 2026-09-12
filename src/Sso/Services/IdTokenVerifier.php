<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Services;

use Codenzia\FilamentPanelBase\Sso\Exceptions\SsoException;
use Codenzia\FilamentPanelBase\Sso\Support\Base64Url;
use Codenzia\FilamentPanelBase\Sso\Support\ProviderConfig;
use Codenzia\FilamentPanelBase\Sso\Support\RsaKeyConverter;

/**
 * Validates an id_token before a single claim inside it is trusted.
 *
 * Every check here is load-bearing; skipping any one of them turns the token
 * into an attacker-supplied JSON blob:
 *
 *   - `alg` comes from a fixed allowlist, read from OUR list and never from
 *     the token header, which is what defeats the classic `alg: none` and
 *     RS→HS confusion attacks.
 *   - The signature is verified against the provider's published JWKS.
 *   - `iss` must equal the issuer the discovery document declared, `aud` must
 *     contain our client_id, and `nonce` must match the value this browser
 *     session generated — so a token minted for a different app, or replayed
 *     from another session, is rejected.
 *   - `exp`/`iat` are both required and checked with a small clock-skew
 *     leeway, and a present `azp` must name this client.
 */
class IdTokenVerifier
{
    /** Seconds of clock skew tolerated on exp/iat. */
    private const LEEWAY = 60;

    /** @var array<string, int> */
    private const ALGORITHMS = [
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
    ];

    /**
     * @param  array<int, array<string, mixed>>  $jwks
     * @return array<string, mixed> the verified claims
     */
    public function verify(
        string $idToken,
        ProviderConfig $provider,
        string $issuer,
        array $jwks,
        string $expectedNonce,
    ): array {
        [$headerSegment, $payloadSegment, $signatureSegment] = $this->split($idToken, $provider);

        $header = $this->decodeJson($headerSegment, $provider, 'header');
        $claims = $this->decodeJson($payloadSegment, $provider, 'payload');

        $algorithm = is_string($header['alg'] ?? null) ? $header['alg'] : '';

        if (! array_key_exists($algorithm, self::ALGORITHMS)) {
            throw $this->reject($provider, "unsupported id_token alg [{$algorithm}]");
        }

        $signature = Base64Url::decode($signatureSegment);

        if ($signature === null || $signature === '') {
            throw $this->reject($provider, 'id_token signature was not decodable');
        }

        $key = $this->selectKey($jwks, is_string($header['kid'] ?? null) ? $header['kid'] : null);

        if ($key === null) {
            throw SsoException::unknownSigningKey(
                'filament-panel-base::auth.sso_provider_error',
                ['provider' => $provider->label],
                'no JWKS key matched the id_token kid',
            );
        }

        $verified = openssl_verify(
            $headerSegment.'.'.$payloadSegment,
            $signature,
            $key,
            self::ALGORITHMS[$algorithm],
        );

        if ($verified !== 1) {
            throw $this->reject($provider, 'id_token signature did not verify');
        }

        $this->assertClaims($claims, $provider, $issuer, $expectedNonce);

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertClaims(
        array $claims,
        ProviderConfig $provider,
        string $issuer,
        string $expectedNonce,
    ): void {
        if (($claims['iss'] ?? null) !== $issuer) {
            throw $this->reject($provider, 'id_token issuer mismatch');
        }

        $audience = $claims['aud'] ?? null;
        $audiences = array_values(array_filter(
            is_array($audience) ? $audience : [$audience],
            'is_string',
        ));

        if (! in_array($provider->clientId, $audiences, true)) {
            throw $this->reject($provider, 'id_token audience mismatch');
        }

        $azp = $claims['azp'] ?? null;

        // `azp` names the party the token was actually issued to. Whenever the
        // provider sends it, it has to be us — a token issued to a different
        // client that merely lists us in `aud` is not ours to accept.
        if ($azp !== null && (! is_string($azp) || ! hash_equals($provider->clientId, $azp))) {
            throw $this->reject($provider, 'id_token azp mismatch');
        }

        // With more than one audience the spec requires azp to be present.
        if (count($audiences) > 1 && ! is_string($azp)) {
            throw $this->reject($provider, 'id_token carried multiple audiences without azp');
        }

        $now = time();

        $expiry = $claims['exp'] ?? null;

        if (! is_numeric($expiry) || (int) $expiry + self::LEEWAY < $now) {
            throw $this->reject($provider, 'id_token expired or missing exp');
        }

        $issuedAt = $claims['iat'] ?? null;

        // `iat` is required by the spec; accepting a token without one means
        // accepting a token whose age cannot be reasoned about at all.
        if (! is_numeric($issuedAt)) {
            throw $this->reject($provider, 'id_token missing or non-numeric iat');
        }

        if ((int) $issuedAt - self::LEEWAY > $now) {
            throw $this->reject($provider, 'id_token issued in the future');
        }

        $nonce = $claims['nonce'] ?? null;

        if (! is_string($nonce) || $expectedNonce === '' || ! hash_equals($expectedNonce, $nonce)) {
            throw SsoException::make(
                'filament-panel-base::auth.sso_invalid_state',
                [],
                'id_token nonce mismatch',
            );
        }

        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw $this->reject($provider, 'id_token carried no sub claim');
        }
    }

    /**
     * Pick the signing key. A `kid` in the header must match exactly; without
     * one, a single unambiguous RSA signing key is accepted.
     *
     * @param  array<int, array<string, mixed>>  $jwks
     */
    private function selectKey(array $jwks, ?string $kid): ?\OpenSSLAsymmetricKey
    {
        $candidates = array_values(array_filter(
            $jwks,
            static fn (array $jwk): bool => ($jwk['kty'] ?? null) === 'RSA'
                && in_array($jwk['use'] ?? 'sig', ['sig', null], true),
        ));

        if ($kid !== null && $kid !== '') {
            foreach ($candidates as $jwk) {
                if (($jwk['kid'] ?? null) === $kid) {
                    return RsaKeyConverter::toPublicKey($jwk);
                }
            }

            return null;
        }

        return count($candidates) === 1
            ? RsaKeyConverter::toPublicKey($candidates[0])
            : null;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function split(string $idToken, ProviderConfig $provider): array
    {
        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            throw $this->reject($provider, 'id_token was not a three-part JWS');
        }

        return [$segments[0], $segments[1], $segments[2]];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $segment, ProviderConfig $provider, string $part): array
    {
        $json = Base64Url::decode($segment);

        if ($json === null) {
            throw $this->reject($provider, "id_token {$part} was not valid base64url");
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw $this->reject($provider, "id_token {$part} was not a JSON object");
        }

        return $decoded;
    }

    private function reject(ProviderConfig $provider, string $reason): SsoException
    {
        return SsoException::make(
            'filament-panel-base::auth.sso_provider_error',
            ['provider' => $provider->label],
            $reason,
        );
    }
}
