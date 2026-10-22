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
use PDOStatement;

use function array_filter;
use function array_keys;
use function is_array;
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
        $pdoStatement = $this->run($sql, $params);

        try {
            $row = self::row($pdoStatement);
        } catch (PDOException $pdoException) {
            throw self::failure($pdoException);
        }

        if (!$row instanceof Row) {
            throw new QueryException('The query returned no row.');
        }

        return $row;
    }

    /**
     * @param string                           $sql    The query, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Row> Every row, in order.
     */
    #[Override]
    public function fetchAll(string $sql, array $params): array
    {
        $pdoStatement = $this->run($sql, $params);

        $rows = [];

        try {
            $row = self::row($pdoStatement);

            while ($row instanceof Row) {
                $rows[] = $row;
                $row = self::row($pdoStatement);
            }
        } catch (PDOException $pdoException) {
            throw self::failure($pdoException);
        }

        return $rows;
    }

    /**
     * @param string                           $sql    A statement, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When the statement fails.
     *
     * @return int The number of rows it affected.
     */
    #[Override]
    public function execute(string $sql, array $params): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @throws QueryException When a transaction cannot begin.
     */
    #[Override]
    public function beginTransaction(): void
    {
        try {
            $this->pdo()->beginTransaction();
        } catch (PDOException $pdoException) {
            throw self::failure($pdoException);
        }
    }

    /**
     * @throws QueryException When the transaction cannot commit.
     */
    #[Override]
    public function commit(): void
    {
        try {
            $this->pdo()->commit();
        } catch (PDOException $pdoException) {
            throw self::failure($pdoException);
        }
    }

    /**
     * @throws QueryException When the transaction cannot roll back.
     */
    #[Override]
    public function rollBack(): void
    {
        try {
            $this->pdo()->rollBack();
        } catch (PDOException $pdoException) {
            throw self::failure($pdoException);
        }
    }

    /**
     * @param string                           $sql    A statement, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When it cannot be prepared or fails.
     *
     * @return PDOStatement The executed statement.
     */
    private function run(string $sql, array $params): PDOStatement
    {
        try {
            $statement = $this->pdo()->prepare($sql);

            if (!$statement instanceof PDOStatement) {
                throw new QueryException('The query could not be prepared.');
            }

            $statement->execute($params);

            return $statement;
        } catch (PDOException $pdoException) {
            throw self::failure($pdoException);
        }
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

    /**
     * Fetch and narrow a row at the PDO boundary. Unsupported values and keys
     * are omitted; no unchecked driver value reaches a Row.
     *
     * @param PDOStatement $pdoStatement The executed query.
     *
     * @throws PDOException When the next row cannot be fetched.
     *
     * @return Row|null The next row, or null at the end of the result.
     */
    private static function row(PDOStatement $pdoStatement): ?Row
    {
        $row = $pdoStatement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $values = [];

        foreach (array_keys(array_filter($row, is_string(...), ARRAY_FILTER_USE_KEY)) as $key) {
            $name = self::name($key);

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
    private static function name(string|int $key): string
    {
        return is_string($key)
            ? $key
            : throw new QueryException('A fetched column must have a string name.');
    }

    /**
     * @param PDOException $pdoException What PDO threw.
     *
     * @return QueryException The failure, naming only its SQLSTATE.
     */
    private static function failure(PDOException $pdoException): QueryException
    {
        return new QueryException('The query failed: SQLSTATE ' . $pdoException->getCode() . '.', 0, $pdoException);
    }
}
