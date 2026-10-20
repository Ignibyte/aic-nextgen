<?php

declare(strict_types=1);

namespace AIC\Session\Domain;

use function random_int;
use function sprintf;

/**
 * Makes session ids: 256 random bits as 64 hex characters. They come from
 * `random_int`, which is cryptographic in PHP and in elephc; elephc has no
 * `random_bytes`.
 */
final readonly class SessionIds
{
    /**
     * The random bytes in an id.
     */
    private const int BYTES = 32;

    /**
     * @return string A fresh id.
     */
    public function fresh(): string
    {
        $id = '';

        for ($byte = 0; $byte < self::BYTES; $byte += 1) {
            $id .= sprintf('%02x', random_int(0, 255));
        }

        return $id;
    }
}
