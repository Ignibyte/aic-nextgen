<?php

declare(strict_types=1);

namespace AIC\Schema\Domain;

use AIC\Schema\Contracts\IndexMethod;
use InvalidArgumentException;

use function implode;

/**
 * An index of a method other than the ordered default (#310): hash, an
 * inverted index, trigrams of text, JSON paths.
 */
final readonly class MethodIndex
{
    /**
     * @param ColumnGroup $columnGroup The columns it indexes.
     * @param IndexMethod $indexMethod How.
     */
    public function __construct(
        public ColumnGroup $columnGroup,
        public IndexMethod $indexMethod,
    ) {
    }

    /**
     * @param Identifier $identifier The table it indexes.
     *
     * @throws InvalidArgumentException When the name would be longer than an identifier may be.
     *
     * @return Identifier Its name: the table, the columns, the method, then `idx`.
     */
    public function indexName(Identifier $identifier): Identifier
    {
        $method = match ($this->indexMethod) {
            IndexMethod::Hash => 'hash',
            IndexMethod::Gin => 'gin',
            IndexMethod::Gist => 'gist',
            IndexMethod::Brin => 'brin',
            IndexMethod::Trigram => 'trigram',
            IndexMethod::JsonPath => 'jsonpath',
        };

        return new Identifier($identifier->value . '_' . implode('_', $this->columnGroup->columns) . '_' . $method . '_idx');
    }
}
