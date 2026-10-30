<?php

declare(strict_types=1);

namespace AIC\Storage\Domain;

use InvalidArgumentException;

use function preg_match;
use function strlen;

/**
 * A stored file's name within its store (#213): path segments joined by `/`,
 * each of letters, digits, `.`, `_` and `-` and none starting with a dot, at
 * most 255 bytes in all. So a key never climbs out of its store, never names
 * a hidden or temporary file, and is already safe in a URL path.
 */
final readonly class StorageKey
{
    /**
     * The grammar of a key.
     */
    private const string GRAMMAR = '#^[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$#';

    /**
     * @param string $value The key, as the module names it (`vehicles/12/3.jpg`).
     *
     * @throws InvalidArgumentException When it breaks the grammar.
     *
     * @psalm-taint-sink client_upload $value
     */
    public function __construct(
        public string $value,
    ) {
        if (strlen($value) > 255 || preg_match(self::GRAMMAR, $value) !== 1) {
            throw new InvalidArgumentException('Not a storage key: "' . $value . '".');
        }
    }
}
