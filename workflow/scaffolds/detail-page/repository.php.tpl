<?php

declare(strict_types=1);

namespace {namespace}\Repository;

use AIC\Component\Application\DetailRows;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The `{table}` row GET {route} shows, found by its `{key}`;
 * made by bin/aic make:detail-page (#341).
 */
final readonly class {stem}DetailRepository
{
    /**
     * The fields the page shows, by name.
     */
    public const array FIELDS = [
{fields}    ];

    /**
     * @param DetailRows $detailRows Finds the row.
     */
    public function __construct(
        private DetailRows $detailRows,
    ) {
    }

    /**
     * @param string $id The key the path names.
     *
     * @throws QueryException           When the row cannot be read.
     * @throws InvalidArgumentException When a field is not an identifier.
     *
     * @return Row|null The row; null when there is none.
     */
    public function find(string $id): ?Row
    {
        return $this->detailRows->find('{table}', '{key}', FieldType::{keyType}, self::FIELDS, $id);
    }
}
