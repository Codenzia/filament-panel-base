<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Disposable Email Blocking
    |--------------------------------------------------------------------------
    |
    | When enabled, registrations are rejected if the email domain matches a
    | host on the curated blocklist below. Admins can toggle this at runtime
    | via AuthenticationSettings; the runtime toggle takes precedence over
    | this config flag.
    |
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Curated Blocklist
    |--------------------------------------------------------------------------
    |
    | Hosts that match (case-insensitive) the email domain or are a suffix of
    | it are rejected. This list is intentionally short — the goal is to block
    | the most common throwaway providers, not to be a comprehensive list.
    | Long lists belong in DNS-based services (e.g. Cloudflare Email Routing,
    | Mailgun, AbuseIPDB) — those don't fit a static array.
    |
    */
    'domains' => [
        '10minutemail.com',
        '10minutemail.net',
        '20minutemail.com',
        'guerrillamail.com',
        'guerrillamail.info',
        'guerrillamail.net',
        'guerrillamail.org',
        'mailinator.com',
        'mailinator.net',
        'mailinator2.com',
        'maildrop.cc',
        'mintemail.com',
        'tempmail.com',
        'temp-mail.org',
        'temp-mail.io',
        'throwawaymail.com',
        'trashmail.com',
        'trashmail.net',
        'yopmail.com',
        'yopmail.net',
        'getnada.com',
        'sharklasers.com',
        'dispostable.com',
        'fakeinbox.com',
        'fakemailgenerator.com',
        'emailondeck.com',
        'tempinbox.com',
        'tempr.email',
        'spamgourmet.com',
        'spam4.me',
        'mohmal.com',
        'easytrashmail.com',
        'instantemailaddress.com',
        'mytemp.email',
        'inboxbear.com',
        'tempemail.co',
        'fakemail.net',
        'mail-temp.com',
        'mailcatch.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra Hosts (per-deployment override)
    |--------------------------------------------------------------------------
    |
    | Set DISPOSABLE_EMAIL_EXTRA in your .env as a pipe-separated host list to
    | add deployment-specific blocks without editing this file.
    |
    */
    'extra' => array_filter(explode('|', (string) env('DISPOSABLE_EMAIL_EXTRA', ''))),
];
