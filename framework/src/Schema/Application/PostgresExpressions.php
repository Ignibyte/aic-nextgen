<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\Arithmetic;
use AIC\Schema\Contracts\Expressions;
use AIC\Schema\Contracts\Operator;
use AIC\Schema\Contracts\Predicate;
use AIC\Schema\Contracts\Term;
use AIC\Schema\Domain\Identifier;
use InvalidArgumentException;
use Override;

use function array_map;
use function implode;
use function preg_match;
use function str_contains;
use function str_replace;

/**
 * The Expressions a SchemaPlan hands its migrations (#310): each term and
 * predicate rendered as Postgres SQL as it is built, every compound one in
 * parentheses, every column an Identifier and every literal written safely. It
 * is the one class that makes a Term or a Predicate (gate:8,
 * `aic.schemaExpression`).
 */
final readonly class PostgresExpressions implements Expressions
{
    /**
     * @param string $name A column of the table the declaration is on.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     *
     * @return Term The column's value.
     */
    #[Override]
    public function column(string $name): Term
    {
        $identifier = new Identifier($name);

        return new Term($identifier->value, [$identifier->value]);
    }

    /**
     * @param int $value A whole number.
     *
     * @return Term It, as a literal; a negative one in parentheses.
     */
    #[Override]
    public function integer(int $value): Term
    {
        return new Term($value < 0 ? '(' . $value . ')' : (string) $value, []);
    }

    /**
     * @param string $value An exact decimal, such as `0.01` or `-12`.
     *
     * @throws InvalidArgumentException When it is not a decimal.
     *
     * @return Term It, as a literal; a negative one in parentheses.
     */
    #[Override]
    public function decimal(string $value): Term
    {
        if (preg_match('/^-?[0-9]+(\\.[0-9]+)?$/', $value) !== 1) {
            throw new InvalidArgumentException('"' . $value . '" is not a decimal: digits, a point, digits; a minus first at most.');
        }

        return new Term(str_contains($value, '-') ? '(' . $value . ')' : $value, []);
    }

    /**
     * @param string $value Any text but a NUL byte.
     *
     * @throws InvalidArgumentException When it holds a NUL byte.
     *
     * @return Term It, in single quotes, each quote in it doubled (Postgres's standard strings).
     */
    #[Override]
    public function text(string $value): Term
    {
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('A text literal cannot hold a NUL byte.');
        }

        return new Term("'" . str_replace("'", "''", $value) . "'", []);
    }

    /**
     * @param bool $value True or false.
     *
     * @return Term It, as `TRUE` or `FALSE`.
     */
    #[Override]
    public function boolean(bool $value): Term
    {
        return new Term($value ? 'TRUE' : 'FALSE', []);
    }

    /**
     * @param Term       $left       The left term.
     * @param Arithmetic $arithmetic What to do with the two.
     * @param Term       $right      The right term.
     *
     * @return Term The result.
     */
    #[Override]
    public function calculate(Term $left, Arithmetic $arithmetic, Term $right): Term
    {
        $operator = match ($arithmetic) {
            Arithmetic::Plus => ' + ',
            Arithmetic::Minus => ' - ',
            Arithmetic::Times => ' * ',
            Arithmetic::DividedBy => ' / ',
        };

        $columns = (new ExpressionParts())->columns([$left->columns, $right->columns]);

        return new Term('(' . $left->sql . $operator . $right->sql . ')', $columns);
    }

    /**
     * @param list<Term> $terms Two or more terms.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Term Their text, joined in order.
     */
    #[Override]
    public function concat(array $terms): Term
    {
        $expressionParts = new ExpressionParts();
        $sql = array_map(static fn (Term $term): string => $term->sql, $terms);
        $columns = array_map(static fn (Term $term): array => $term->columns, $terms);

        return new Term($expressionParts->joined($sql, ' || '), $expressionParts->columns($columns));
    }

    /**
     * @param Term $term A text term.
     *
     * @return Term It in lower case.
     */
    #[Override]
    public function lower(Term $term): Term
    {
        return new Term('lower(' . $term->sql . ')', $term->columns);
    }

    /**
     * @param Term $term A text term.
     *
     * @return Term Its length, in characters.
     */
    #[Override]
    public function length(Term $term): Term
    {
        return new Term('char_length(' . $term->sql . ')', $term->columns);
    }

    /**
     * @param Term     $left     The left term.
     * @param Operator $operator How the two compare.
     * @param Term     $right    The right term.
     *
     * @return Predicate Whether they compare so.
     */
    #[Override]
    public function compare(Term $left, Operator $operator, Term $right): Predicate
    {
        $sign = match ($operator) {
            Operator::Equal => ' = ',
            Operator::NotEqual => ' <> ',
            Operator::Less => ' < ',
            Operator::LessOrEqual => ' <= ',
            Operator::Greater => ' > ',
            Operator::GreaterOrEqual => ' >= ',
        };

        $columns = (new ExpressionParts())->columns([$left->columns, $right->columns]);

        return new Predicate('(' . $left->sql . $sign . $right->sql . ')', $columns);
    }

    /**
     * @param Term $term A term.
     *
     * @return Predicate Whether it is null.
     */
    #[Override]
    public function isNull(Term $term): Predicate
    {
        return new Predicate('(' . $term->sql . ' IS NULL)', $term->columns);
    }

    /**
     * @param Term       $term   A term.
     * @param list<Term> $values One or more terms it may equal.
     *
     * @throws InvalidArgumentException When there are none.
     *
     * @return Predicate Whether it equals one of them.
     */
    #[Override]
    public function oneOf(Term $term, array $values): Predicate
    {
        if ($values === []) {
            throw new InvalidArgumentException('oneOf() takes one or more values.');
        }

        $sql = [];
        $columns = [$term->columns];

        foreach ($values as $value) {
            $sql[] = $value->sql;
            $columns[] = $value->columns;
        }

        return new Predicate('(' . $term->sql . ' IN (' . implode(', ', $sql) . '))', (new ExpressionParts())->columns($columns));
    }

    /**
     * @param list<Predicate> $predicates Two or more predicates.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Predicate Whether every one holds.
     */
    #[Override]
    public function all(array $predicates): Predicate
    {
        return $this->joined($predicates, ' AND ');
    }

    /**
     * @param list<Predicate> $predicates Two or more predicates.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Predicate Whether at least one holds.
     */
    #[Override]
    public function any(array $predicates): Predicate
    {
        return $this->joined($predicates, ' OR ');
    }

    /**
     * @param Predicate $predicate A predicate.
     *
     * @return Predicate Whether it does not hold.
     */
    #[Override]
    public function not(Predicate $predicate): Predicate
    {
        return new Predicate('(NOT ' . $predicate->sql . ')', $predicate->columns);
    }

    /**
     * @param list<Predicate> $predicates Two or more predicates.
     * @param string          $glue       What joins them, `AND` or `OR`.
     *
     * @throws InvalidArgumentException When there are fewer than two.
     *
     * @return Predicate Them joined, in parentheses.
     */
    private function joined(array $predicates, string $glue): Predicate
    {
        $expressionParts = new ExpressionParts();
        $sql = array_map(static fn (Predicate $predicate): string => $predicate->sql, $predicates);
        $columns = array_map(static fn (Predicate $predicate): array => $predicate->columns, $predicates);

        return new Predicate($expressionParts->joined($sql, $glue), $expressionParts->columns($columns));
    }
}
