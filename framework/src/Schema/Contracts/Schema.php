<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

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
}
