<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\Defaults;
use AIC\Schema\Contracts\Expressions;
use AIC\Schema\Contracts\Term;
use InvalidArgumentException;
use Override;

use function json_validate;

/**
 * Postgres's spelling of the common defaults (#339): `now()`, and a JSON
 * document quoted as `Expressions::text()` quotes it, cast to jsonb. With
 * `PostgresExpressions`, the one other class that makes a `Term` (gate:8,
 * `aic.schemaExpression`).
 */
final readonly class PostgresDefaults implements Defaults
{
    /**
     * @param Expressions $expressions Quotes a text literal.
     */
    public function __construct(
        private Expressions $expressions = new PostgresExpressions(),
    ) {
    }

    /**
     * @return Term `now()`.
     */
    #[Override]
    public function now(): Term
    {
        return new Term('now()', []);
    }

    /**
     * @param string $value A JSON document.
     *
     * @throws InvalidArgumentException When it is not JSON, or holds a NUL byte.
     *
     * @return Term It, quoted, cast to jsonb.
     */
    #[Override]
    public function json(string $value): Term
    {
        if (!json_validate($value)) {
            throw new InvalidArgumentException('A jsonb default must be JSON.');
        }

        return new Term($this->expressions->text($value)->sql . '::jsonb', []);
    }
}
