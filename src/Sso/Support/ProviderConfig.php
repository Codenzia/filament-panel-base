<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Support;

/**
 * A single, fully-resolved SSO provider entry.
 *
 * Instances only exist for providers that are actually usable: the factory
 * returns null when credentials or a discovery URL are missing, so the login
 * screen can never render a button that leads nowhere.
 *
 * "Presets" are not a separate code path — Google and Entra are ordinary OIDC
 * providers whose issuer happens to be known, so the preset only supplies a
 * default issuer that an explicit `issuer`/`discovery_url` still overrides.
 */
final class ProviderConfig
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $icon,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly string $discoveryUrl,
        public readonly array $scopes,
        public readonly bool $allowUnverifiedEmail,
        public readonly bool $autoApprove,
    ) {}

    /**
     * Build a provider from its raw config array, or return null when it is
     * not completely configured.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $key, array $config): ?self
    {
        $clientId = self::str($config['client_id'] ?? null);
        $clientSecret = self::str($config['client_secret'] ?? null);
        $discoveryUrl = self::resolveDiscoveryUrl($key, $config);

        if ($clientId === '' || $clientSecret === '' || $discoveryUrl === '') {
            return null;
        }

        $scopes = $config['scopes'] ?? ['openid', 'profile', 'email'];
        $scopes = array_values(array_filter(array_map(
            static fn ($scope): string => trim((string) $scope),
            is_array($scopes) ? $scopes : [],
        )));

        // `openid` is what makes this OIDC rather than bare OAuth2 — without
        // it the provider is not obliged to return an id_token at all.
        if (! in_array('openid', $scopes, true)) {
            array_unshift($scopes, 'openid');
        }

        $label = self::str($config['label'] ?? null);
        $icon = self::str($config['icon'] ?? null);

        return new self(
            key: $key,
            label: $label !== '' ? $label : ucfirst($key),
            icon: $icon !== '' ? $icon : null,
            clientId: $clientId,
            clientSecret: $clientSecret,
            discoveryUrl: $discoveryUrl,
            scopes: $scopes,
            allowUnverifiedEmail: (bool) ($config['allow_unverified_email'] ?? false),
            autoApprove: (bool) ($config['auto_approve'] ?? false),
        );
    }

    /**
     * Precedence: explicit discovery_url → explicit issuer → preset issuer.
     *
     * @param  array<string, mixed>  $config
     */
    private static function resolveDiscoveryUrl(string $key, array $config): string
    {
        $discoveryUrl = self::str($config['discovery_url'] ?? null);

        if ($discoveryUrl !== '') {
            return $discoveryUrl;
        }

        $issuer = self::str($config['issuer'] ?? null);

        if ($issuer === '') {
            $issuer = self::presetIssuer(
                self::str($config['preset'] ?? null) ?: $key,
                $config,
            );
        }

        return $issuer === ''
            ? ''
            : rtrim($issuer, '/').'/.well-known/openid-configuration';
    }

    /**
     * Known issuers for the convenience presets.
     *
     * @param  array<string, mixed>  $config
     */
    private static function presetIssuer(string $preset, array $config): string
    {
        return match (strtolower($preset)) {
            'google' => 'https://accounts.google.com',
            'entra', 'azure', 'microsoft' => sprintf(
                'https://login.microsoftonline.com/%s/v2.0',
                self::str($config['tenant'] ?? null) ?: 'common',
            ),
            default => '',
        };
    }

    private static function str(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}
