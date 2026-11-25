<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

use InvalidArgumentException;

/**
 * The terms a column's default is most often (#339), beside `Expressions`'
 * literals: the time the transaction began and a JSON document. A migration
 * takes them from `Schema::defaults()` and hands one to `types()->defaulted()`.
 */
interface Defaults
{
    /**
     * @return Term The time the transaction began, `now()`.
     */
    public function now(): Term;

    /**
     * @param string $value A JSON document.
     *
     * @throws InvalidArgumentException When it is not JSON, or holds a NUL byte.
     *
     * @return Term It as a jsonb literal, such as `'[]'::jsonb`.
     */
    public function json(string $value): Term;
}
