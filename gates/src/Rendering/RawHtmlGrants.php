<?php

declare(strict_types=1);

namespace Gates\Rendering;

use AIC\Component\Contracts\RawHtmlGrant;
use InvalidArgumentException;

use function array_key_exists;
use function is_array;
use function is_file;

/**
 * Reads `config/raw-html.php` for gate:27 (#99): a list of RawHtmlGrant, one
 * per class. Only the gate reads it; the app never does.
 */
final readonly class RawHtmlGrants
{
    /**
     * @param string $root The repo root, or a plant root.
     *
     * @throws InvalidArgumentException When the file returns anything but a list of grants, one per class, or a grant is malformed.
     *
     * @return array<string, RawHtmlGrant> The grants, by class; none when the file is absent.
     */
    public function read(string $root): array
    {
        $grants = [];

        foreach (self::listed($root . '/config/raw-html.php') as $rawHtmlGrant) {
            if (array_key_exists($rawHtmlGrant->class, $grants)) {
                throw new InvalidArgumentException('it grants ' . $rawHtmlGrant->class . ' twice; give each class one grant.');
            }

            $grants[$rawHtmlGrant->class] = $rawHtmlGrant;
        }

        return $grants;
    }

    /**
     * @param string $file The grants file.
     *
     * @throws InvalidArgumentException When it returns anything but an array of grants, or a grant in it is malformed.
     *
     * @return list<RawHtmlGrant> Its grants, in order; none when it is absent.
     */
    private static function listed(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $listed = require $file;

        if (!is_array($listed)) {
            throw new InvalidArgumentException('it returns a list of RawHtmlGrant values.');
        }

        $grants = [];

        foreach ($listed as $grant) {
            if (!$grant instanceof RawHtmlGrant) {
                throw new InvalidArgumentException('it returns a list of RawHtmlGrant values.');
            }

            $grants[] = $grant;
        }

        return $grants;
    }
}
