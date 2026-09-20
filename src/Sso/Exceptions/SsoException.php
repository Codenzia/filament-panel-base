<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Exceptions;

use RuntimeException;

/**
 * A recoverable SSO failure that the user is allowed to see.
 *
 * The exception carries a *translation key*, never a raw provider message, so
 * the controller can flash something friendly without risking protocol detail
 * (or anything derived from a token) reaching the browser. The technical
 * reason travels in `$reason` for logging only.
 */
class SsoException extends RuntimeException
{
    /**
     * True when the failure was "the token was signed by a key we have never
     * seen". A cached JWKS that predates a key rotation looks exactly like a
     * forged token, so the caller is allowed to refetch the key set once and
     * retry before giving up.
     */
    public bool $signingKeyUnknown = false;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly string $translationKey,
        public readonly array $params = [],
        public readonly string $reason = '',
    ) {
        parent::__construct($reason !== '' ? $reason : $translationKey);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function make(string $key, array $params = [], string $reason = ''): self
    {
        return new self($key, $params, $reason);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function unknownSigningKey(string $key, array $params = [], string $reason = ''): self
    {
        $exception = new self($key, $params, $reason);
        $exception->signingKeyUnknown = true;

        return $exception;
    }

    public function userMessage(): string
    {
        return (string) fpb_trans($this->translationKey, $this->params);
    }
}
