<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Database\Contracts\Rows;
use AIC\Kernel\Domain\Monotonic;
use AIC\Profiler\Domain\StatementRecord;
use Override;

use function count;

/**
 * The database a profiled request runs on (#229): each statement goes to the
 * root's database and is told to the profile as its SQL text, its time and
 * its row count. The values bound to it pass through and are never recorded.
 */
final readonly class ProfiledDatabase implements Database
{
    /**
     * @param Database  $database  The database that runs the statements.
     * @param Profile   $profile   The request's profile.
     * @param Monotonic $monotonic The clock the times are read from.
     */
    public function __construct(
        private Database $database,
        private Profile $profile,
        private Monotonic $monotonic = new Monotonic(),
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
        $started = $this->monotonic->microseconds();
        $row = $this->database->fetchOne($sql, $params);
        $this->told($sql, $started, 1);

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
        $started = $this->monotonic->microseconds();
        $rows = $this->database->fetchAll($sql, $params);
        $this->told($sql, $started, count($rows->all()));

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
        $started = $this->monotonic->microseconds();
        $affected = $this->database->execute($sql, $params);
        $this->told($sql, $started, $affected);

        return $affected;
    }

    /**
     * @throws QueryException When a transaction cannot begin.
     */
    #[Override]
    public function beginTransaction(): void
    {
        $started = $this->monotonic->microseconds();
        $this->database->beginTransaction();
        $this->told('BEGIN', $started, 0);
    }

    /**
     * @throws QueryException When the transaction cannot commit.
     */
    #[Override]
    public function commit(): void
    {
        $started = $this->monotonic->microseconds();
        $this->database->commit();
        $this->told('COMMIT', $started, 0);
    }

    /**
     * @throws QueryException When the transaction cannot roll back.
     */
    #[Override]
    public function rollBack(): void
    {
        $started = $this->monotonic->microseconds();
        $this->database->rollBack();
        $this->told('ROLLBACK', $started, 0);
    }

    /**
     * @param string $sql     The statement's text.
     * @param int    $started When it began, by the monotonic clock.
     * @param int    $rows    The rows it read or changed.
     */
    private function told(string $sql, int $started, int $rows): void
    {
        $this->profile->statement(new StatementRecord($sql, $this->monotonic->microseconds() - $started, $rows));
    }
}
