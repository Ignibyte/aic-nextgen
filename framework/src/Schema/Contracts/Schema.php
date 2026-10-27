<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

use InvalidArgumentException;

/**
 * What a migration describes its changes on.
 */
interface Schema
{
    /**
     * @param string $table The new table's name.
     *
     * @return TableBuilder Its columns, added in order.
     */
    public function create(string $table): TableBuilder;

    /**
     * @return ColumnTypes Every column type a migration can name, for `TableAlteration::addColumn()` and
     *                     `changeType()` (#86).
     */
    public function types(): ColumnTypes;

    /**
     * @param string $table A table an earlier migration created.
     *
     * @return TableAlteration Its columns to add or change, and its constraints and indexes to add.
     */
    public function alter(string $table): TableAlteration;

    /**
     * @param string $name A Postgres extension the migration needs, such as `pgcrypto`.
     */
    public function extension(string $name): void;

    /**
     * Creates the view, or replaces it with this SELECT (#191): a report its module reads through a
     * read-only entity type. Its SQL is the one place a module writes SQL.
     *
     * @param string $name   The view's name, an identifier.
     * @param string $select The SELECT it shows, over tables its module's migrations or requires create.
     *
     * @throws InvalidArgumentException When the name is not an identifier, or the SELECT is empty.
     */
    public function view(string $name, string $select): void;
}
