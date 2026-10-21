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
     * @param string $table A table an earlier migration created.
     *
     * @return TableAlteration The constraints and indexes to add to it.
     */
    public function alter(string $table): TableAlteration;

    /**
     * @param string $name A Postgres extension the migration needs, such as `pgcrypto`.
     */
    public function extension(string $name): void;
}
