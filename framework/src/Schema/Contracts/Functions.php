<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

use InvalidArgumentException;

/**
 * The database functions a check, a partial index or a generated column is
 * most often written with (#344), part of `Expressions`: text trimmed and
 * matched, a number rounded, the first term that is not null, how many terms
 * are, a JSON value's kind and length, and a predicate's truth as a value.
 */
interface Functions
{
    /**
     * @param Term $term A text term.
     *
     * @return Term It without the spaces at either end.
     */
    public function trim(Term $term): Term;

    /**
     * @param Term $term   A numeric term.
     * @param int  $places How many decimal places to keep, from 0.
     *
     * @throws InvalidArgumentException When the places are below 0.
     *
     * @return Term It, rounded.
     */
    public function round(Term $term, int $places): Term;

    /**
     * @param list<Term> $terms Two or more terms.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Term The first that is not null; null when every one is.
     */
    public function coalesce(array $terms): Term;

    /**
     * @param list<Term> $terms One or more terms.
     *
     * @throws InvalidArgumentException When there are none.
     *
     * @return Term How many of them are not null.
     */
    public function countPresent(array $terms): Term;

    /**
     * @param Term $term A JSON term.
     *
     * @return Term Its kind: `object`, `array`, `string`, `number`, `boolean` or `null`.
     */
    public function jsonKind(Term $term): Term;

    /**
     * @param Term $term A JSON array.
     *
     * @return Term How many elements it has; the database refuses a value that is no array.
     */
    public function jsonLength(Term $term): Term;

    /**
     * @param Predicate $predicate A predicate.
     *
     * @return Term Its truth as a value, so two conditions can be compared.
     */
    public function truth(Predicate $predicate): Term;

    /**
     * @param Term   $term    A text term.
     * @param string $pattern A Postgres regular expression.
     *
     * @throws InvalidArgumentException When the pattern holds a NUL byte.
     *
     * @return Predicate Whether the text matches it, by case.
     */
    public function matches(Term $term, string $pattern): Predicate;
}
