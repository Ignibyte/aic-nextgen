<?php

declare(strict_types=1);

namespace AIC\Broadcast\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * A broadcast channel's name (#314): 1 to 100 lower-case letters and digits
 * in parts joined by `.` or `-`, so a module names its own as
 * `module.what`. It is a column value, never SQL: every channel shares the
 * one Postgres channel `aic_broadcasts`.
 */
final readonly class Channel
{
    /**
     * @param string $name The name, such as `ledger.feed`.
     *
     * @throws InvalidArgumentException When it is not one.
     */
    public function __construct(
        public string $name,
    ) {
        if (preg_match('/^(?=.{1,100}$)[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException('"' . $name . '" is not a channel: lower-case letters and digits, joined by . or -.');
        }
    }
}
