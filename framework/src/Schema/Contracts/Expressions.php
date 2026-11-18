<?php

declare(strict_types=1);

namespace AIC\Schema\Contracts;

use InvalidArgumentException;

/**
 * The typed expressions a migration declares checks, partial indexes and
 * generated columns with (#310), from `Schema::expressions()`. Each answer is
 * already rendered by the dialect, so a module writes no SQL.
 */
interface Expressions
{
    /**
     * @param string $name A column of the table the declaration is on.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     *
     * @return Term The column's value.
     */
    public function column(string $name): Term;

    /**
     * @param int $value A whole number.
     *
     * @return Term It, as a literal.
     */
    public function integer(int $value): Term;

    /**
     * @param string $value An exact decimal, such as `0.01` or `-12`.
     *
     * @throws InvalidArgumentException When it is not a decimal.
     *
     * @return Term It, as a literal.
     */
    public function decimal(string $value): Term;

    /**
     * @param string $value Any text but a NUL byte.
     *
     * @throws InvalidArgumentException When it holds a NUL byte.
     *
     * @return Term It, as a quoted literal.
     */
    public function text(string $value): Term;

    /**
     * @param bool $value True or false.
     *
     * @return Term It, as a literal.
     */
    public function boolean(bool $value): Term;

    /**
     * @param Term       $left       The left term.
     * @param Arithmetic $arithmetic What to do with the two.
     * @param Term       $right      The right term.
     *
     * @return Term The result.
     */
    public function calculate(Term $left, Arithmetic $arithmetic, Term $right): Term;

    /**
     * @param list<Term> $terms Two or more terms.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Term Their text, joined in order.
     */
    public function concat(array $terms): Term;

    /**
     * @param Term $term A text term.
     *
     * @return Term It in lower case.
     */
    public function lower(Term $term): Term;

    /**
     * @param Term $term A text term.
     *
     * @return Term Its length, in characters.
     */
    public function length(Term $term): Term;

    /**
     * @param Term     $left     The left term.
     * @param Operator $operator How the two compare.
     * @param Term     $right    The right term.
     *
     * @return Predicate Whether they compare so.
     */
    public function compare(Term $left, Operator $operator, Term $right): Predicate;

    /**
     * @param Term $term A term.
     *
     * @return Predicate Whether it is null.
     */
    public function isNull(Term $term): Predicate;

    /**
     * @param Term       $term   A term.
     * @param list<Term> $values One or more terms it may equal.
     *
     * @throws InvalidArgumentException When there are none.
     *
     * @return Predicate Whether it equals one of them.
     */
    public function oneOf(Term $term, array $values): Predicate;

    /**
     * @param list<Predicate> $predicates Two or more predicates.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Predicate Whether every one holds.
     */
    public function all(array $predicates): Predicate;

    /**
     * @param list<Predicate> $predicates Two or more predicates.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Predicate Whether at least one holds.
     */
    public function any(array $predicates): Predicate;

    /**
     * @param Predicate $predicate A predicate.
     *
     * @return Predicate Whether it does not hold.
     */
    public function not(Predicate $predicate): Predicate;
}
