<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Support;

use Illuminate\Support\Facades\Hash;

/**
 * The `users.password` value for accounts created by a provider rather than
 * chosen by a person.
 *
 * Storing a random hash makes such an account look like it has a password its
 * owner has never been told, which is how "you can disconnect your last social
 * account, you still have a password" turns into a lockout (PNB-035).
 *
 * The stored value is the empty string: `Hash::check()` returns false for it
 * without ever reaching a hashing driver, so nothing can authenticate against
 * it, and the column stays non-null for the many schemas that require it. A
 * password reset writes an ordinary hash over it and the account gains a real
 * sign-in method.
 */
final class UnusablePassword
{
    /**
     * A stored value that cannot authenticate.
     */
    public static function make(): string
    {
        return '';
    }

    /**
     * True when the stored value cannot be used to sign in: absent, empty, or
     * not a hash any configured driver recognises.
     */
    public static function is(mixed $password): bool
    {
        if (! is_string($password) || $password === '') {
            return true;
        }

        return Hash::info($password)['algoName'] === 'unknown';
    }
}
