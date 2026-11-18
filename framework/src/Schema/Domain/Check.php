<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\Predicate;
use InvalidArgumentException;

/**
 * A named CHECK constraint (#310): what every row of the table holds.
 */
final readonly class Check
{
    /**
     * @param Identifier $name      Its name, joined to the table's for the constraint's.
     * @param Predicate  $predicate What every row holds.
     */
    public function __construct(
        public Identifier $name,
        public Predicate $predicate,
    ) {
    }

    /**
     * @param Identifier $identifier The table it is on.
     *
     * @throws InvalidArgumentException When the name would be longer than an identifier may be.
     *
     * @return Identifier The constraint's name, `<table>_<name>`.
     */
    public function constraintName(Identifier $identifier): Identifier
    {
        return new Identifier($identifier->value . '_' . $this->name->value);
    }
}
