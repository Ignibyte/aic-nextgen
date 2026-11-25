<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\Predicate;
use AIC\Schema\Contracts\Term;
use InvalidArgumentException;
use Override;

use function array_map;
use function count;
use function implode;

/**
 * Postgres's spelling of `Functions` (#344), in a trait so
 * `PostgresExpressions`, which takes it, keeps its size and stays the one
 * class that makes these terms.
 */
trait PostgresFunctions
{
    /**
     * @param Term $term A text term.
     *
     * @return Term `btrim(…)`.
     */
    #[Override]
    public function trim(Term $term): Term
    {
        return new Term('btrim(' . $term->sql . ')', $term->columns);
    }

    /**
     * @param Term $term   A numeric term.
     * @param int  $places How many decimal places to keep, from 0.
     *
     * @throws InvalidArgumentException When the places are below 0.
     *
     * @return Term `round(…, places)`.
     */
    #[Override]
    public function round(Term $term, int $places): Term
    {
        if ($places < 0) {
            throw new InvalidArgumentException('A number is rounded to 0 places or more, not ' . $places . '.');
        }

        return new Term('round(' . $term->sql . ', ' . $places . ')', $term->columns);
    }

    /**
     * @param list<Term> $terms Two or more terms.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Term `coalesce(…)`.
     */
    #[Override]
    public function coalesce(array $terms): Term
    {
        return $this->called('coalesce', $terms, 2);
    }

    /**
     * @param list<Term> $terms One or more terms.
     *
     * @throws InvalidArgumentException When there are none.
     *
     * @return Term `num_nonnulls(…)`.
     */
    #[Override]
    public function countPresent(array $terms): Term
    {
        return $this->called('num_nonnulls', $terms, 1);
    }

    /**
     * @param Term $term A JSON term.
     *
     * @return Term `jsonb_typeof(…)`.
     */
    #[Override]
    public function jsonKind(Term $term): Term
    {
        return new Term('jsonb_typeof(' . $term->sql . ')', $term->columns);
    }

    /**
     * @param Term $term A JSON array.
     *
     * @return Term `jsonb_array_length(…)`.
     */
    #[Override]
    public function jsonLength(Term $term): Term
    {
        return new Term('jsonb_array_length(' . $term->sql . ')', $term->columns);
    }

    /**
     * @param Predicate $predicate A predicate.
     *
     * @return Term It, in parentheses as every predicate is.
     */
    #[Override]
    public function truth(Predicate $predicate): Term
    {
        return new Term($predicate->sql, $predicate->columns);
    }

    /**
     * @param Term   $term    A text term.
     * @param string $pattern A Postgres regular expression.
     *
     * @throws InvalidArgumentException When the pattern holds a NUL byte.
     *
     * @return Predicate `(… ~ 'pattern')`.
     */
    #[Override]
    public function matches(Term $term, string $pattern): Predicate
    {
        return new Predicate('(' . $term->sql . ' ~ ' . $this->text($pattern)->sql . ')', $term->columns);
    }

    /**
     * @param string     $function A function that takes a list of terms.
     * @param list<Term> $terms    The terms.
     * @param int        $least    How many it takes at least.
     *
     * @throws InvalidArgumentException When there are fewer.
     *
     * @return Term The call.
     */
    private function called(string $function, array $terms, int $least): Term
    {
        if (count($terms) < $least) {
            throw new InvalidArgumentException($function . '() takes ' . $least . ' or more terms.');
        }

        $sql = array_map(static fn (Term $term): string => $term->sql, $terms);
        $columns = array_map(static fn (Term $term): array => $term->columns, $terms);

        return new Term($function . '(' . implode(', ', $sql) . ')', (new ExpressionParts())->columns($columns));
    }
}
