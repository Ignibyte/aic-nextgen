<?php

declare(strict_types=1);

namespace AIC\Session\Domain;

use function random_int;
use function sprintf;

/**
 * Makes unguessable tokens, session ids and CSRF tokens among them: 256
 * random bits as 64 hex characters. They come from `random_int`, which is
 * cryptographic in PHP and in elephc; elephc has no `random_bytes`.
 */
final readonly class RandomTokens
{
    /**
     * The random bytes in a token.
     */
    private const int BYTES = 32;

    /**
     * @return string A fresh token.
     */
    public function fresh(): string
    {
        $token = '';

        for ($byte = 0; $byte < self::BYTES; $byte += 1) {
            $token .= sprintf('%02x', random_int(0, 255));
        }

        return $token;
    }
}
