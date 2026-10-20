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
     * @param string $name A Postgres extension the migration needs, such as `pgcrypto`.
     */
    public function extension(string $name): void;
}
