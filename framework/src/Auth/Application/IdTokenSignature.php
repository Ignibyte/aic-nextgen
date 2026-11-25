<?php

declare(strict_types=1);

namespace AIC\Auth\Application;

use AIC\Auth\Contracts\SignInRefusedException;
use AIC\Auth\Domain\JsonWebKey;
use AIC\Auth\Domain\JsonWebKeys;
use AIC\Auth\Domain\OidcProvider;
use AIC\Auth\Domain\PublicKeyDer;
use AIC\Session\Domain\StringMapJson;

use function base64_decode;
use function count;
use function explode;
use function in_array;
use function is_string;
use function openssl_pkey_get_public;
use function openssl_verify;
use function strlen;
use function strtr;

use const OPENSSL_ALGO_SHA256;

/**
 * An id token's signature, checked before any of its claims is read (#343,
 * OpenID Connect Core 3.1.3.7 item 6): its header's `alg` must be one the
 * provider declares (never `none`), the key the header's `kid` names (or the
 * one key of its type) must be of that algorithm's type, and the signature
 * over the header and the payload must verify with SHA-256, RSA PKCS#1 v1.5
 * for RS256 and ECDSA over P-256 for ES256.
 */
final readonly class IdTokenSignature
{
    /**
     * @param StringMapJson $stringMapJson Reads the header.
     * @param PublicKeyDer  $publicKeyDer  Writes the key's PEM and an ES256 signature's DER.
     */
    public function __construct(
        private StringMapJson $stringMapJson = new StringMapJson(),
        private PublicKeyDer $publicKeyDer = new PublicKeyDer(),
    ) {
    }

    /**
     * @param string       $idToken      A compact JWT.
     * @param OidcProvider $oidcProvider The provider it came from, with the algorithms it signs with.
     * @param JsonWebKeys  $jsonWebKeys  The provider's keys.
     *
     * @throws SignInRefusedException When it is not signed, by an algorithm the provider does not use, by none of
     *                                its keys, or its signature does not verify.
     */
    public function check(string $idToken, OidcProvider $oidcProvider, JsonWebKeys $jsonWebKeys): void
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw self::refused('it cannot be read');
        }

        $header = $this->stringMapJson->decode(self::bytes($parts[0]));
        $algorithm = self::algorithm($header['alg'] ?? '', $oidcProvider);
        $jsonWebKey = $jsonWebKeys->key($header['kid'] ?? '', $algorithm === 'ES256' ? 'EC' : 'RSA');
        $signature = $algorithm === 'ES256'
            ? $this->publicKeyDer->ecdsa(self::bytes($parts[2]))
            : self::bytes($parts[2]);

        if (!self::verifies($parts[0] . '.' . $parts[1], $signature, $this->pem($algorithm, $jsonWebKey))) {
            throw self::refused('its signature does not verify');
        }
    }

    /**
     * @param string     $algorithm  `RS256` or `ES256`.
     * @param JsonWebKey $jsonWebKey The key the header names.
     *
     * @throws SignInRefusedException When the key is of another type or curve, or for another algorithm.
     *
     * @return string The key as PEM.
     */
    private function pem(string $algorithm, JsonWebKey $jsonWebKey): string
    {
        if ($jsonWebKey->alg !== '' && $jsonWebKey->alg !== $algorithm) {
            throw self::refused('its key is for another algorithm');
        }

        return $algorithm === 'RS256'
            ? $this->publicKeyDer->rsa($jsonWebKey->modulus, $jsonWebKey->exponent)
            : $this->p256($jsonWebKey);
    }

    /**
     * @param JsonWebKey $jsonWebKey An EC key.
     *
     * @throws SignInRefusedException When it is not on P-256, or a coordinate is not 32 bytes.
     *
     * @return string The key as PEM.
     */
    private function p256(JsonWebKey $jsonWebKey): string
    {
        if ($jsonWebKey->crv !== 'P-256' || strlen($jsonWebKey->pointX) !== 32 || strlen($jsonWebKey->pointY) !== 32) {
            throw self::refused('its key is not a P-256 key');
        }

        return $this->publicKeyDer->p256($jsonWebKey->pointX, $jsonWebKey->pointY);
    }

    /**
     * @param string       $algorithm    The header's `alg`.
     * @param OidcProvider $oidcProvider The provider.
     *
     * @throws SignInRefusedException When it is `none`, or not one of the provider's algorithms.
     *
     * @return string It.
     */
    private static function algorithm(string $algorithm, OidcProvider $oidcProvider): string
    {
        if (in_array($algorithm, $oidcProvider->algorithms, true)) {
            return $algorithm;
        }

        throw self::refused($algorithm === 'none' ? 'it is not signed' : 'it is not signed by an algorithm the provider uses');
    }

    /**
     * @param string $signed    The header and the payload, as the token carries them.
     * @param string $signature The signature openssl reads.
     * @param string $pem       The key.
     *
     * @return bool Whether the key is a public key and the signature verifies with SHA-256.
     */
    private static function verifies(string $signed, string $signature, string $pem): bool
    {
        $publicKey = openssl_pkey_get_public($pem);

        return $publicKey !== false && openssl_verify($signed, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @param string $base64url A base64url part of the token.
     *
     * @return string Its bytes; empty when it is not base64url.
     */
    private static function bytes(string $base64url): string
    {
        $bytes = base64_decode(strtr($base64url, '-_', '+/'), true);

        return is_string($bytes)
            ? $bytes
            : '';
    }

    /**
     * @param string $reason Why.
     *
     * @return SignInRefusedException The refusal, with the reason for the person.
     */
    private static function refused(string $reason): SignInRefusedException
    {
        return new SignInRefusedException("The provider's id token was refused: " . $reason . '.');
    }
}
