<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Rules;

use Closure;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Restrict self-registration to an allowlist of email domains — e.g. only
 * `@acme.com` staff may sign up. When the allowlist is EMPTY the rule is a
 * no-op (any domain is accepted), so existing installs are unaffected until
 * an admin opts in.
 *
 * A domain entry matches its exact host and any subdomain (an entry of
 * `acme.com` accepts `jo@acme.com` and `jo@eu.acme.com`). The rule is
 * zero-cost — it reads the configured list and never makes DNS / HTTP calls.
 * Admin settings take precedence; falls back to the config-file default so a
 * stale install still behaves sensibly. Format violations are left to the
 * `email` rule; this rule only judges the host.
 */
final class AllowedEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $allowed = $this->allowlist();

        if ($allowed === []) {
            return; // No allowlist configured → every domain is permitted.
        }

        if (! is_string($value) || ! str_contains($value, '@')) {
            return; // Format violations are handled by the `email` rule.
        }

        $host = strtolower(trim(substr(strrchr($value, '@') ?: '@', 1)));

        if ($host === '') {
            return;
        }

        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return;
            }
        }

        $fail(__('filament-panel-base::auth.email_domain_not_allowed', ['attribute' => $attribute]));
    }

    /**
     * Normalised list of permitted domains. Admin setting wins; config is the
     * fallback. A leading `@` or whitespace on any entry is tolerated.
     *
     * @return array<int, string>
     */
    private function allowlist(): array
    {
        try {
            /** @var array<int, string> $domains */
            $domains = app(AuthenticationSettings::class)->allowed_email_domains;
        } catch (\Throwable) {
            /** @var array<int, string> $domains */
            $domains = config('filament-panel-base.allowed_email_domains', []);
        }

        return array_values(array_filter(array_unique(array_map(
            static fn (string $domain): string => ltrim(strtolower(trim($domain)), '@'),
            $domains,
        ))));
    }
}
