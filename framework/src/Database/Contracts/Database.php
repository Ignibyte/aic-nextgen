<?php

declare(strict_types=1);

namespace AIC\Database\Contracts;

/**
 * The database, as the app sees it: parameterised queries with `?`
 * placeholders, returning typed rows, transactions, and Postgres
 * notifications on the request's own connection (#314).
 */
interface Database
{
    /**
     * @param string                           $sql    The query, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When the query fails or returns no row.
     *
     * @return Row The first row.
     *
     * @psalm-taint-sink client_upload $sql
     * @psalm-taint-sink client_upload $params
     */
    public function fetchOne(string $sql, array $params): Row;

    /**
     * @param string                           $sql    The query, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When the query fails.
     *
     * @return Rows Every row, in order.
     *
     * @psalm-taint-sink client_upload $sql
     * @psalm-taint-sink client_upload $params
     */
    public function fetchAll(string $sql, array $params): Rows;

    /**
     * @param string                           $sql    A statement, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When the statement fails.
     *
     * @return int The number of rows it affected.
     *
     * @psalm-taint-sink client_upload $sql
     * @psalm-taint-sink client_upload $params
     */
    public function execute(string $sql, array $params): int;

    /**
     * @throws QueryException When a transaction cannot begin.
     */
    public function beginTransaction(): void;

    /**
     * @throws QueryException When the transaction cannot commit.
     */
    public function commit(): void;

    /**
     * @throws QueryException When the transaction cannot roll back.
     */
    public function rollBack(): void;

    /**
     * @return bool Whether a transaction is open (#309), so a write that records its event joins it.
     */
    public function inTransaction(): bool;

    /**
     * Starts listening on a channel for the rest of the request (LISTEN), so `notification()` returns what is
     * NOTIFYed on it after this.
     *
     * @param string $channel A lower-case identifier, 1 to 63 characters; never app input.
     *
     * @throws QueryException When the name is not one, or LISTEN fails.
     */
    public function listen(string $channel): void;

    /**
     * @param int $timeoutMs How long to wait, in milliseconds; 0 only takes one already here.
     *
     * @throws QueryException When the wait fails.
     *
     * @return string The next notification's payload on a channel listened to; empty when none came in time.
     */
    public function notification(int $timeoutMs): string;
}
