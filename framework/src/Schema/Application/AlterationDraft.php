<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\TableAlteration;
use AIC\Schema\Domain\Alteration;
use AIC\Schema\Domain\Identifier;
use InvalidArgumentException;
use Override;

/**
 * The TableAlteration a migration chains on: it collects what to add to an
 * existing table, and gives the Alteration.
 */
final readonly class AlterationDraft implements TableAlteration
{
    /**
     * @param Identifier      $identifier      The existing table's name.
     * @param ConstraintDraft $constraintDraft Collects what to add.
     */
    public function __construct(
        private Identifier $identifier,
        private ConstraintDraft $constraintDraft,
    ) {
    }

    /**
     * @param list<string> $columns Columns whose values, taken together, no two rows may share.
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return self The builder.
     */
    #[Override]
    public function unique(array $columns): self
    {
        $this->constraintDraft->unique($columns);

        return $this;
    }

    /**
     * @param list<string> $columns The columns to index, in order.
     *
     * @throws InvalidArgumentException When the list is empty.
     *
     * @return self The builder.
     */
    #[Override]
    public function index(array $columns): self
    {
        $this->constraintDraft->index($columns);

        return $this;
    }

    /**
     * @throws InvalidArgumentException When nothing was added.
     *
     * @return Alteration The alteration as built.
     */
    public function alteration(): Alteration
    {
        return new Alteration($this->identifier, $this->constraintDraft->constraints());
    }
}
