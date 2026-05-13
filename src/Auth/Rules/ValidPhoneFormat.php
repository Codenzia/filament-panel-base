<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Strict E.164 / international-format validation for phone numbers.
 *
 * Uses propaganistas/laravel-phone (libphonenumber wrapper) when installed.
 * Falls back to a simple regex when the library is not present so the rule
 * never fatals — only the strict validation requires the optional dep.
 *
 * Format-only — no SMS, no OTP, no provider lookup. Numbers that parse
 * cleanly through libphonenumber and are flagged as valid for the supplied
 * country (or as a fully-qualified international number when no country is
 * provided) pass; everything else is rejected.
 */
final class ValidPhoneFormat implements ValidationRule
{
    /**
     * @param  array<int, string>  $countries  ISO 3166-1 alpha-2 codes (e.g. ['JO', 'AE']).
     *                                         Empty array = require a leading +country-code.
     */
    public function __construct(
        private readonly array $countries = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // `required` owns null/empty.
        }

        if (! is_string($value)) {
            $fail(__('panel-base::auth.phone_invalid', ['attribute' => $attribute]));

            return;
        }

        $candidate = trim($value);

        if (class_exists(\Propaganistas\LaravelPhone\PhoneNumber::class)) {
            try {
                $phone = new \Propaganistas\LaravelPhone\PhoneNumber($candidate, $this->countries);

                if (! $phone->isValid()) {
                    $fail(__('panel-base::auth.phone_invalid', ['attribute' => $attribute]));
                }

                return;
            } catch (\Propaganistas\LaravelPhone\Exceptions\NumberParseException) {
                $fail(__('panel-base::auth.phone_format_invalid', ['attribute' => $attribute]));

                return;
            }
        }

        // Fallback regex when libphonenumber is unavailable: accept E.164 shape only.
        if (! preg_match('/^\+\d{7,15}$/', $candidate)) {
            $fail(__('panel-base::auth.phone_format_invalid', ['attribute' => $attribute]));
        }
    }
}
