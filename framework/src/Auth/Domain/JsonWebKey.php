<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

/**
 * One public key of a provider's JSON Web Key Set (#343, RFC 7517): its id,
 * its type (`RSA` or `EC`), the algorithm it is for when it says, its curve,
 * and its components as bytes, base64url-decoded. A component a key does not
 * carry, or one that is not base64url, is empty.
 */
final readonly class JsonWebKey
{
    /**
     * @param string $kid      The key's id (`kid`); empty when it has none.
     * @param string $kty      Its type (`kty`), `RSA` or `EC`.
     * @param string $alg      The algorithm it is for (`alg`), such as `RS256`; empty when it does not say.
     * @param string $crv      An EC key's curve (`crv`), such as `P-256`.
     * @param string $modulus  An RSA key's modulus (`n`).
     * @param string $exponent An RSA key's exponent (`e`).
     * @param string $pointX   An EC key's x coordinate (`x`).
     * @param string $pointY   An EC key's y coordinate (`y`).
     */
    public function __construct(
        public string $kid,
        public string $kty,
        public string $alg,
        public string $crv,
        public string $modulus,
        public string $exponent,
        public string $pointX,
        public string $pointY,
    ) {
    }
}
