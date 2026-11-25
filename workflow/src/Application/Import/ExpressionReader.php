<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\CodeCall;
use AIC\Workflow\Domain\Import\CodeList;
use AIC\Workflow\Domain\Import\CodeNode;
use AIC\Workflow\Domain\Import\CodeValue;
use AIC\Workflow\Exception\UnsupportedSql;

use function count;

/**
 * A deparsed Postgres expression, as pg_dump writes a check, a partial index's `WHERE`, a default or a generated
 * column, read into `Schema::expressions()` calls (#318): `OR`, `AND`, `NOT`, the six comparisons, `IS [NOT] NULL`,
 * `IS TRUE`, `~` (#344) and `= ANY (ARRAY[…])` here, the terms in `TermReader`.
 */
final readonly class ExpressionReader
{
    private const array OPERATORS = [
        '!=' => 'NotEqual',
        '<' => 'Less',
        '<=' => 'LessOrEqual',
        '<>' => 'NotEqual',
        '=' => 'Equal',
        '>' => 'Greater',
        '>=' => 'GreaterOrEqual',
    ];

    /**
     * @param TermReader      $termReader      Reads the terms.
     * @param ConditionValues $conditionValues Makes a condition a value and a value a condition (#344).
     */
    public function __construct(
        private TermReader $termReader = new TermReader(),
        private ConditionValues $conditionValues = new ConditionValues(),
    ) {
    }

    /**
     * @param TokenCursor $tokenCursor The expression's tokens, all read.
     *
     * @throws UnsupportedSql When it is not a predicate Schema's expressions can state.
     *
     * @return CodeCall The predicate.
     */
    public function predicate(TokenCursor $tokenCursor): CodeCall
    {
        $codeNode = $this->expression($tokenCursor);
        $tokenCursor->end();

        return $this->conditionValues->asPredicate($codeNode);
    }

    /**
     * @param TokenCursor $tokenCursor At a term, such as a default; read up to the first word that does not continue it.
     *
     * @throws UnsupportedSql When it is not a term Schema's expressions can state.
     *
     * @return CodeNode The term.
     */
    public function term(TokenCursor $tokenCursor): CodeNode
    {
        return $this->conditionValues->asTerm($this->termReader->sum($tokenCursor, $this->grouped(...)));
    }

    /**
     * @param TokenCursor $tokenCursor At an expression.
     *
     * @throws UnsupportedSql When a part is unsupported.
     *
     * @return CodeNode The expression: a predicate or a term.
     */
    public function expression(TokenCursor $tokenCursor): CodeNode
    {
        $parts = [$this->conjunction($tokenCursor)];

        while ($tokenCursor->accept('or')) {
            $parts[] = $this->conjunction($tokenCursor);
        }

        return $this->joined('any', $parts);
    }

    /**
     * @param TokenCursor $tokenCursor A parenthesized group's or a function argument's tokens.
     *
     * @throws UnsupportedSql When a part is unsupported, or tokens are left that no part reads.
     *
     * @return CodeNode Its expression, every token read.
     */
    public function grouped(TokenCursor $tokenCursor): CodeNode
    {
        $codeNode = $this->expression($tokenCursor);
        $tokenCursor->end();

        return $codeNode;
    }

    /**
     * @param TokenCursor $tokenCursor At a conjunction.
     *
     * @throws UnsupportedSql When a part is unsupported.
     *
     * @return CodeNode It.
     */
    private function conjunction(TokenCursor $tokenCursor): CodeNode
    {
        $parts = [$this->negation($tokenCursor)];

        while ($tokenCursor->accept('and')) {
            $parts[] = $this->negation($tokenCursor);
        }

        return $this->joined('all', $parts);
    }

    /**
     * @param TokenCursor $tokenCursor At a possible `NOT`.
     *
     * @throws UnsupportedSql When a part is unsupported.
     *
     * @return CodeNode It.
     */
    private function negation(TokenCursor $tokenCursor): CodeNode
    {
        return $tokenCursor->accept('not')
            ? new CodeCall('not', [$this->conditionValues->asPredicate($this->negation($tokenCursor))])
            : $this->comparison($tokenCursor);
    }

    /**
     * @param TokenCursor $tokenCursor At a comparison or a lone term.
     *
     * @throws UnsupportedSql When a part is unsupported.
     *
     * @return CodeNode It.
     */
    private function comparison(TokenCursor $tokenCursor): CodeNode
    {
        $codeNode = $this->termReader->sum($tokenCursor, $this->grouped(...));

        return match (true) {
            $tokenCursor->accept('is', 'not', 'null') => new CodeCall('not', [$this->isNull($codeNode)]),
            $tokenCursor->accept('is', 'null') => $this->isNull($codeNode),
            $tokenCursor->accept('is', 'true') => $this->conditionValues->known($codeNode),
            $tokenCursor->accept('~') => new CodeCall('matches', [$this->conditionValues->asTerm($codeNode), $this->pattern($tokenCursor)]),
            $tokenCursor->accept('=', 'any') => $this->oneOf($tokenCursor, $codeNode),
            $tokenCursor->accept('<>', 'all') => new CodeCall('not', [$this->oneOf($tokenCursor, $codeNode)]),
            default => $this->compared($tokenCursor, $codeNode),
        };
    }

    /**
     * @param CodeNode $codeNode A term.
     *
     * @return CodeCall `isNull(…)`.
     */
    private function isNull(CodeNode $codeNode): CodeCall
    {
        return new CodeCall('isNull', [$this->conditionValues->asTerm($codeNode)]);
    }

    /**
     * @param TokenCursor $tokenCursor After the term, at a possible operator.
     * @param CodeNode    $codeNode    The term.
     *
     * @throws UnsupportedSql When a side is a predicate.
     *
     * @return CodeNode The comparison, or the term when no operator follows.
     */
    private function compared(TokenCursor $tokenCursor, CodeNode $codeNode): CodeNode
    {
        foreach (self::OPERATORS as $sign => $operator) {
            if ($tokenCursor->accept($sign)) {
                $right = $this->termReader->sum($tokenCursor, $this->grouped(...));

                return new CodeCall('compare', [
                    $this->conditionValues->asTerm($codeNode),
                    new CodeValue('Operator::' . $operator),
                    $this->conditionValues->asTerm($right),
                ]);
            }
        }

        return $codeNode;
    }

    /**
     * @param TokenCursor $tokenCursor After `~`.
     *
     * @throws UnsupportedSql When the pattern is not a text literal.
     *
     * @return CodeValue The pattern, as PHP.
     */
    private function pattern(TokenCursor $tokenCursor): CodeValue
    {
        $codeNode = $this->termReader->sum($tokenCursor, $this->grouped(...));
        $pattern = $codeNode instanceof CodeCall && $codeNode->method === 'text'
            ? $codeNode->valueArgument(0)
            : null;

        return $pattern ?? throw new UnsupportedSql('a match against something other than a text literal has no Schema expression');
    }

    /**
     * @param TokenCursor $tokenCursor After `= ANY` or `<> ALL`, at the parenthesized array.
     * @param CodeNode    $codeNode    The term compared.
     *
     * @throws UnsupportedSql When the group is not an `ARRAY[…]`.
     *
     * @return CodeCall `oneOf(…)`.
     */
    private function oneOf(TokenCursor $tokenCursor, CodeNode $codeNode): CodeCall
    {
        $group = $tokenCursor->group();
        $values = $this->termReader->sum($group, $this->grouped(...));
        $group->end();

        if (!$values instanceof CodeList) {
            throw new UnsupportedSql('= ANY over something other than ARRAY[…] has no Schema expression');
        }

        return new CodeCall('oneOf', [$this->conditionValues->asTerm($codeNode), $values]);
    }

    /**
     * @param string         $method `all` or `any`.
     * @param list<CodeNode> $parts  The parts; one stands alone.
     *
     * @throws UnsupportedSql When a part is not a predicate.
     *
     * @return CodeNode The parts joined, flattening a nested join of the same kind.
     */
    private function joined(string $method, array $parts): CodeNode
    {
        if (count($parts) === 1) {
            return $parts[0];
        }

        $predicates = [];

        foreach ($parts as $part) {
            $predicates = [...$predicates, ...$this->flattened($method, $this->conditionValues->asPredicate($part))];
        }

        return new CodeCall($method, [new CodeList($predicates)]);
    }

    /**
     * @param string   $method   `all` or `any`.
     * @param CodeCall $codeCall A part.
     *
     * @return list<CodeNode> The part's own parts when it is a join of the same kind; otherwise the part.
     */
    private function flattened(string $method, CodeCall $codeCall): array
    {
        $nested = $codeCall->method === $method
            ? $codeCall->listArgument(0)
            : null;

        return $nested instanceof CodeList
            ? $nested->items
            : [$codeCall];
    }
}
