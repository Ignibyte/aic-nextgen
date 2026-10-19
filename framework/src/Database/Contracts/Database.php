<?php

declare(strict_types=1);

namespace AIC\Database\Contracts;

/**
 * The database, as the app sees it: parameterised queries with `?`
 * placeholders, returning typed rows, and transactions.
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
     */
    public function fetchOne(string $sql, array $params): Row;

    /**
     * @param string                           $sql    The query, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Row> Every row, in order.
     */
    public function fetchAll(string $sql, array $params): array;

    /**
     * @param string                           $sql    A statement, with `?` placeholders.
     * @param list<string|int|float|bool|null> $params The values for the placeholders, in order.
     *
     * @throws QueryException When the statement fails.
     *
     * @return int The number of rows it affected.
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
}
