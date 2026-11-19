<?php

declare(strict_types=1);

namespace AIC\Database\Infrastructure;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use PDO;
use PDOException;
use PDOStatement;

use function array_filter;
use function array_keys;
use function is_array;
use function is_scalar;
use function is_string;

use const ARRAY_FILTER_USE_KEY;

/**
 * Fetches and narrows a row at the PDO boundary, out of `PdoDatabase` (#314):
 * unsupported values and keys are omitted, so no unchecked driver value
 * reaches a Row.
 */
final readonly class PdoRows
{
    /**
     * @param PDOStatement $pdoStatement The executed query.
     *
     * @throws PDOException When the next row cannot be fetched.
     *
     * @return Row|null The next row, or null at the end of the result.
     */
    public function next(PDOStatement $pdoStatement): ?Row
    {
        $row = $pdoStatement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $values = [];

        foreach (array_keys(array_filter($row, is_string(...), ARRAY_FILTER_USE_KEY)) as $key) {
            $name = $this->name($key);

            if (!is_scalar($row[$key]) && $row[$key] !== null) {
                continue;
            }

            $values[$name] = $row[$key];
        }

        return new Row($values);
    }

    /**
     * @param string|int $key A key after the driver row's name filter.
     *
     * @throws QueryException When an unsupported numeric key reaches the normalizer.
     *
     * @return string The column name.
     */
    private function name(string|int $key): string
    {
        return is_string($key)
            ? $key
            : throw new QueryException('A fetched column must have a string name.');
    }
}
