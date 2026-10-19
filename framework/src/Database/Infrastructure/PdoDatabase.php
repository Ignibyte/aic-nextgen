<?php

declare(strict_types=1);

namespace AIC\Database\Infrastructure;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Database\Domain\DatabaseConfig;
use Override;
use PDO;
use PDOException;

use function array_filter;
use function array_merge;
use function is_array;
use function is_null;
use function is_scalar;
use function is_string;

use const ARRAY_FILTER_USE_KEY;

/**
 * The database over PDO pgsql. The connection opens on the first query and
 * serves the worker for its life; it is held in a one-slot list, the form
 * elephc keeps an object's type in (plan.md, native style).
 */
final class PdoDatabase implements Database
{
    /**
     * The open connection in a one-slot list; empty until the first query.
     *
     * @var list<PDO>
     */
    private array $connection = [];

    /**
     * @param DatabaseConfig $databaseConfig Where the database is.
     */
    public function __construct(
        private readonly DatabaseConfig $databaseConfig,
    ) {
    }

    /**
     * @param string                           $sql    The query, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When the query fails or returns no row.
     *
     * @return Row The first row.
     */
    #[Override]
    public function fetchOne(string $sql, array $params): Row
    {
        try {
            $statement = $this->pdo()->prepare($sql);

            if ($statement === false) {
                throw new QueryException('The query could not be prepared.');
            }

            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $pdoException) {
            throw new QueryException('The query failed: SQLSTATE ' . $pdoException->getCode() . '.', 0, $pdoException);
        }

        if (!is_array($row)) {
            throw new QueryException('The query returned no row.');
        }

        // Narrowed to scalars and nulls under string keys, into fresh arrays: the
        // native form for data out of `mixed` (plan.md).
        $scalars = array_filter(array_filter($row, is_scalar(...)), is_string(...), ARRAY_FILTER_USE_KEY);
        $nulls = array_filter(array_filter($row, is_null(...)), is_string(...), ARRAY_FILTER_USE_KEY);

        return new Row(array_merge($scalars, $nulls));
    }

    /**
     * @throws PDOException When the connection cannot be opened.
     *
     * @return PDO The connection, opened on first use.
     */
    private function pdo(): PDO
    {
        $open = $this->connection[0] ?? null;

        if ($open instanceof PDO) {
            return $open;
        }

        $config = $this->databaseConfig;
        $pdo = new PDO(
            'pgsql:host=' . $config->host . ';port=' . $config->port . ';dbname=' . $config->database,
            $config->user,
            $config->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $this->connection = [$pdo];

        return $pdo;
    }
}
