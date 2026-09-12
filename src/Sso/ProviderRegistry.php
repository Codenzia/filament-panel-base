<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso;

use Codenzia\FilamentPanelBase\Sso\Support\ProviderConfig;

/**
 * The single place that answers "which SSO providers may this request use?".
 *
 * Both the login-screen buttons and the redirect/callback controller resolve
 * providers through here, so a provider that is disabled or half-configured
 * is invisible to the UI *and* unreachable over HTTP — the button list and
 * the route guard can never disagree.
 */
class ProviderRegistry
{
    public function isEnabled(): bool
    {
        return (bool) config('filament-panel-base.sso.enabled', false);
    }

    /**
     * Every usable provider, keyed by provider key. Empty while the module is
     * disabled.
     *
     * @return array<string, ProviderConfig>
     */
    public function all(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        /** @var array<string, mixed> $providers */
        $providers = (array) config('filament-panel-base.sso.providers', []);

        $resolved = [];

        foreach ($providers as $key => $config) {
            if (! is_string($key) || $key === '' || ! is_array($config)) {
                continue;
            }

            $provider = ProviderConfig::fromArray($key, $config);

            if ($provider instanceof ProviderConfig) {
                $resolved[$key] = $provider;
            }
        }

        return $resolved;
    }

    public function find(string $key): ?ProviderConfig
    {
        return $this->all()[$key] ?? null;
    }

    public function hasAny(): bool
    {
        return $this->all() !== [];
    }
}
