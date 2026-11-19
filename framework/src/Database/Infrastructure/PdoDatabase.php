<?php

declare(strict_types=1);

namespace AIC\Database\Infrastructure;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Database\Contracts\Rows;
use AIC\Database\Domain\DatabaseConfig;
use Override;
use PDO;
use Pdo\Pgsql;
use PDOException;
use PDOStatement;

use function array_key_exists;
use function is_array;
use function is_string;
use function max;
use function preg_match;

/**
 * The database over PDO pgsql (a `Pdo\Pgsql`, for LISTEN and its
 * notifications, #314). The connection opens on the first query and serves
 * the request for its life; it is held in a one-slot list, the form elephc
 * keeps an object's type in (plan.md, native style).
 */
final class PdoDatabase implements Database
{
    /**
     * The open connection in a one-slot list; empty until the first query.
     *
     * @var list<Pgsql>
     */
    private array $connection = [];

    /**
     * @param DatabaseConfig $databaseConfig Where the database is.
     * @param PdoRows        $pdoRows        Narrows each fetched row.
     */
    public function __construct(
        private readonly DatabaseConfig $databaseConfig,
        private readonly PdoRows $pdoRows = new PdoRows(),
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
            $row = $this->pdoRows->next($pdoStatement);
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
     * @return Rows Every row, in order.
     */
    #[Override]
    public function fetchAll(string $sql, array $params): Rows
    {
        $pdoStatement = $this->run($sql, $params);

        $rows = [];

        try {
            $row = $this->pdoRows->next($pdoStatement);

            while ($row instanceof Row) {
                $rows[] = $row;
                $row = $this->pdoRows->next($pdoStatement);
            }
        } catch (PDOException $pdoException) {
            throw self::failure($pdoException);
        }

        return new Rows($rows);
    }

    /**
     * @return bool Whether a transaction is open (#309); with no connection yet, none is, and asking opens none.
     */
    #[Override]
    public function inTransaction(): bool
    {
        $open = $this->connection[0] ?? null;

        return $open instanceof Pgsql && $open->inTransaction();
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
     * @param string $channel A lower-case identifier, 1 to 63 characters.
     *
     * @throws QueryException When the name is not one, or LISTEN fails.
     */
    #[Override]
    public function listen(string $channel): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $channel) !== 1) {
            throw new QueryException('"' . $channel . '" is not a channel: a lower-case identifier of up to 63 characters.');
        }

        try {
            $this->pdo()->exec('LISTEN ' . $channel);
        } catch (PDOException $pdoException) {
            throw self::failure($pdoException);
        }
    }

    /**
     * @param int $timeoutMs How long to wait, in milliseconds; 0 only takes one already here.
     *
     * @throws QueryException When the wait fails.
     *
     * @return string The next notification's payload on a channel listened to; empty when none came in time.
     */
    #[Override]
    public function notification(int $timeoutMs): string
    {
        try {
            $notice = $this->pdo()->getNotify(PDO::FETCH_ASSOC, max(0, $timeoutMs));

            if (!is_array($notice) || !array_key_exists('payload', $notice) || !is_string($notice['payload'])) {
                return '';
            }

            return $notice['payload'];
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
     * @return Pgsql The connection, opened on first use.
     */
    private function pdo(): Pgsql
    {
        $open = $this->connection[0] ?? null;

        if ($open instanceof Pgsql) {
            return $open;
        }

        $config = $this->databaseConfig;
        $pgsql = new Pgsql(
            'pgsql:host=' . $config->host . ';port=' . $config->port . ';dbname=' . $config->database,
            $config->user,
            $config->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $this->connection = [$pgsql];

        return $pgsql;
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
