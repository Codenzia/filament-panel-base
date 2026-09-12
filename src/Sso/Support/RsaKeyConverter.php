<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sso\Support;

use OpenSSLAsymmetricKey;

/**
 * Turns an RSA JWK (`{"kty":"RSA","n":...,"e":...}`) into an OpenSSL public
 * key by hand-assembling the DER SubjectPublicKeyInfo structure and handing
 * the resulting PEM to `openssl_pkey_get_public()`.
 *
 * The obvious shortcut — `openssl_pkey_new(['rsa' => ['n' => ..., 'e' => ...]])`
 * — is deliberately NOT used: that call reads `openssl.cnf`, so it fails hard
 * on any host whose OpenSSL config is missing or unreadable (a common state on
 * Windows/Herd and in slim containers). Parsing a PEM needs no config file, so
 * this path works everywhere.
 *
 * DER shape (RFC 5280 §4.1 / RFC 8017 A.1.1):
 *
 *   SEQUENCE {
 *     SEQUENCE { OBJECT IDENTIFIER rsaEncryption, NULL }
 *     BIT STRING { SEQUENCE { INTEGER modulus, INTEGER exponent } }
 *   }
 */
final class RsaKeyConverter
{
    /** DER for OBJECT IDENTIFIER 1.2.840.113549.1.1.1 (rsaEncryption). */
    private const OID_RSA_ENCRYPTION = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";

    /**
     * @param  array<string, mixed>  $jwk
     */
    public static function toPublicKey(array $jwk): ?OpenSSLAsymmetricKey
    {
        if (($jwk['kty'] ?? null) !== 'RSA') {
            return null;
        }

        $modulus = is_string($jwk['n'] ?? null) ? Base64Url::decode($jwk['n']) : null;
        $exponent = is_string($jwk['e'] ?? null) ? Base64Url::decode($jwk['e']) : null;

        if ($modulus === null || $exponent === null || $modulus === '' || $exponent === '') {
            return null;
        }

        $publicKey = self::sequence(
            self::integer($modulus).self::integer($exponent)
        );

        $subjectPublicKeyInfo = self::sequence(
            self::sequence(self::OID_RSA_ENCRYPTION."\x05\x00")
            .self::bitString($publicKey)
        );

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);

        return $key === false ? null : $key;
    }

    private static function sequence(string $contents): string
    {
        return "\x30".self::length(strlen($contents)).$contents;
    }

    /**
     * DER INTEGER. Leading zero bytes are dropped, then one is prepended when
     * the high bit is set so the value stays unsigned.
     */
    private static function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '') {
            $bytes = "\x00";
        }

        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".self::length(strlen($bytes)).$bytes;
    }

    /**
     * DER BIT STRING with a leading "0 unused bits" octet.
     */
    private static function bitString(string $contents): string
    {
        $contents = "\x00".$contents;

        return "\x03".self::length(strlen($contents)).$contents;
    }

    /**
     * DER definite length: short form below 128, otherwise long form.
     */
    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
