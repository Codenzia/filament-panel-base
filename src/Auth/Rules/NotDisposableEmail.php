<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Rules;

use Closure;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject emails whose host (or any parent host) appears in the curated
 * disposable-mail blocklist at `config/disposable_emails.php`.
 *
 * The rule is zero-cost — it loads a static list at boot and never makes
 * DNS / HTTP lookups. Admin toggle (AuthenticationSettings) takes
 * precedence; falls back to the config-file default so a stale install
 * still behaves sensibly.
 */
final class NotDisposableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! is_string($value) || ! str_contains($value, '@')) {
            return; // Format violations are handled by the `email` rule.
        }

        $host = strtolower(trim(substr(strrchr($value, '@') ?: '@', 1)));

        if ($host === '') {
            return;
        }

        foreach ($this->blocklist() as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                $fail(__('filament-panel-base::auth.email_disposable', ['attribute' => $attribute]));

                return;
            }
        }
    }

    private function isEnabled(): bool
    {
        try {
            return app(AuthenticationSettings::class)->disposable_email_blocking;
        } catch (\Throwable) {
            return (bool) config('disposable_emails.enabled', true);
        }
    }

    /**
     * @return array<int, string>
     */
    private function blocklist(): array
    {
        /** @var array<int, string> $base */
        $base = config('disposable_emails.domains', []);

        /** @var array<int, string> $extra */
        $extra = config('disposable_emails.extra', []);

        return array_unique(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            [...$base, ...$extra],
        ));
    }
}
