<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

use InvalidArgumentException;

use function preg_match;
use function trim;

/**
 * One class's grant to build RawHtml (#99), as `config/raw-html.php` lists
 * it: the class, exactly how many places in it build one, and why. gate:27
 * holds every use to its grant, so a new use changes a committed line.
 *
 * @api
 */
final readonly class RawHtmlGrant
{
    /**
     * @param string $class   The class that builds RawHtml, fully qualified.
     * @param int    $uses    How many `new RawHtml` it holds, at least one.
     * @param string $because Why its markup is trusted.
     *
     * @throws InvalidArgumentException When the class is not a class name, the count is under one, or the reason is empty.
     */
    public function __construct(
        public string $class,
        public int $uses,
        public string $because,
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $class) !== 1) {
            throw new InvalidArgumentException('A RawHtml grant names a fully qualified class; got "' . $class . '".');
        }

        if ($uses < 1 || trim($because) === '') {
            throw new InvalidArgumentException('A RawHtml grant for ' . $class . ' needs at least one use and a reason.');
        }
    }
}
