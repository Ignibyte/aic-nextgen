<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use InvalidArgumentException;

use function mb_trim;

/**
 * A view a migration creates or replaces (#191): its name and the SELECT it
 * shows.
 */
final readonly class View
{
    /**
     * @param Identifier $identifier The view's name.
     * @param string     $select     The SELECT it shows.
     *
     * @throws InvalidArgumentException When the SELECT is empty.
     */
    public function __construct(
        public Identifier $identifier,
        public string $select,
    ) {
        if (mb_trim($select) === '') {
            throw new InvalidArgumentException('The view ' . $identifier->value . ' shows a SELECT; got none.');
        }
    }
}
