<?php

declare(strict_types=1);

namespace AIC\Socket\Domain;

use function explode;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Reads an upgrade request's head (#32): the request line's method and path
 * (its query dropped), and each header by its lower-case name.
 */
final readonly class UpgradeParser
{
    /**
     * @param string $head The request line and headers, joined by CRLF, without the blank line.
     *
     * @return UpgradeHead Them, read.
     */
    public function read(string $head): UpgradeHead
    {
        $lines = explode("\r\n", $head);
        $request = explode(' ', $lines[0]);
        $target = $request[1] ?? '';
        $query = strpos($target, '?');
        $headers = [];

        foreach ($lines as $line) {
            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $headers[strtolower(trim(substr($line, 0, $colon)))] = trim(substr($line, $colon + 1));
        }

        return new UpgradeHead($request[0], $query === false ? $target : substr($target, 0, $query), $headers);
    }
}
