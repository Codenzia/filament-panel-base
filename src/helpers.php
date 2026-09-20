<?php

declare(strict_types=1);

if (! function_exists('fpb_trans')) {
    /**
     * Translate one of this package's own catalogue strings.
     *
     * The chrome this package ships is keyed by its English text so that a
     * host can override any of it from `lang/{locale}.json` without
     * publishing anything. Laravel resolves such a key as a translation
     * *group* whenever the JSON catalogue misses it, so a host that ships
     * `lang/{locale}/User.php` — or, on a case-insensitive filesystem,
     * `lang/{locale}/user.php` — makes `__('User')` hand back that whole file
     * as an array. Printed, that is an "Array to string conversion"; returned
     * from a typed method, a TypeError. Either way the panel 500s.
     *
     * The override behaviour is kept: this is `__()` with the key as the
     * fallback for anything that does not come back as a string.
     *
     * @param  array<string, mixed>  $replace
     */
    function fpb_trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $line = trans($key, $replace, $locale);

        return is_string($line) ? $line : $key;
    }
}
