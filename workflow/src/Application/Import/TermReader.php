<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\CodeCall;
use AIC\Workflow\Domain\Import\CodeList;
use AIC\Workflow\Domain\Import\CodeNode;
use AIC\Workflow\Domain\Import\CodeValue;
use AIC\Workflow\Domain\Import\DumpToken;
use AIC\Workflow\Domain\Import\DumpTokenKind;
use AIC\Workflow\Exception\UnsupportedSql;
use Closure;

use function in_array;
use function preg_replace;
use function str_contains;
use function str_replace;
use function strtolower;

/**
 * The terms of a deparsed Postgres expression (#318): arithmetic, `||`, the casts pg_dump adds, literals, columns,
 * the functions `FunctionReader` knows and `ARRAY[…]`. A parenthesized group is read by the whole expression reader it
 * is handed.
 */
final readonly class TermReader
{
    private const array ARITHMETIC = ['+' => 'Plus', '-' => 'Minus', '*' => 'Times', '/' => 'DividedBy'];

    private const array KEPT_CASTS = [
        'text',
        'character varying',
        'character',
        'bpchar',
        'numeric',
        'integer',
        'bigint',
        'smallint',
        'text[]',
        'character varying[]',
    ];

    /**
     * @param PhpLiteral     $phpLiteral     Writes string literals.
     * @param FunctionReader $functionReader Reads the function calls.
     */
    public function __construct(
        private PhpLiteral $phpLiteral = new PhpLiteral(),
        private FunctionReader $functionReader = new FunctionReader(),
    ) {
    }

    /**
     * @param TokenCursor                    $tokenCursor At the term.
     * @param Closure(TokenCursor): CodeNode $inner       Reads a parenthesized group's whole expression.
     *
     * @throws UnsupportedSql When the term has something Schema's expressions cannot state.
     *
     * @return CodeNode The term, read; a parenthesized predicate comes back as it is.
     */
    public function sum(TokenCursor $tokenCursor, Closure $inner): CodeNode
    {
        $node = $this->product($tokenCursor, $inner);

        while (true) {
            if ($tokenCursor->accept('||')) {
                $node = $this->concat($node, $this->product($tokenCursor, $inner));
                continue;
            }

            $operator = $this->operator($tokenCursor, ['+', '-']);

            if ($operator === '') {
                return $node;
            }

            $node = $this->calculate($node, $operator, $this->product($tokenCursor, $inner));
        }
    }

    /**
     * @param TokenCursor                    $tokenCursor At the product.
     * @param Closure(TokenCursor): CodeNode $inner       Reads a group.
     *
     * @throws UnsupportedSql When a factor is unsupported.
     *
     * @return CodeNode The product.
     */
    private function product(TokenCursor $tokenCursor, Closure $inner): CodeNode
    {
        $node = $this->cast($tokenCursor, $inner);
        $operator = $this->operator($tokenCursor, ['*', '/']);

        while ($operator !== '') {
            $node = $this->calculate($node, $operator, $this->cast($tokenCursor, $inner));
            $operator = $this->operator($tokenCursor, ['*', '/']);
        }

        return $node;
    }

    /**
     * @param TokenCursor                    $tokenCursor At a factor.
     * @param Closure(TokenCursor): CodeNode $inner       Reads a group.
     *
     * @throws UnsupportedSql When a cast is to a type no term keeps.
     *
     * @return CodeNode The factor without the casts pg_dump writes.
     */
    private function cast(TokenCursor $tokenCursor, Closure $inner): CodeNode
    {
        $node = $this->primary($tokenCursor, $inner);

        while ($tokenCursor->accept('::')) {
            $type = $this->castType($tokenCursor);

            if (!in_array($type, self::KEPT_CASTS, true)) {
                throw new UnsupportedSql('a cast to ' . $type . ' has no Schema expression');
            }

            $node = $type === 'numeric'
                ? $this->decimal($node)
                : $node;
        }

        return $node;
    }

    /**
     * @param TokenCursor                    $tokenCursor At a primary.
     * @param Closure(TokenCursor): CodeNode $inner       Reads a group.
     *
     * @throws UnsupportedSql When it is a function, literal or name Schema's expressions lack.
     *
     * @return CodeNode It.
     */
    private function primary(TokenCursor $tokenCursor, Closure $inner): CodeNode
    {
        $dumpToken = $tokenCursor->peek() ?? throw new UnsupportedSql('an expression ends early');

        return match (true) {
            $dumpToken->isSymbol('(') => $inner($tokenCursor->group()),
            $dumpToken->isSymbol('-') => $this->negative($tokenCursor),
            $dumpToken->isWord('array') => $this->array($tokenCursor, $inner),
            $dumpToken->dumpTokenKind === DumpTokenKind::Number => $this->number($tokenCursor->next()->text),
            $dumpToken->dumpTokenKind === DumpTokenKind::Text => $this->call('text', $this->phpLiteral->string($tokenCursor->next()->text)),
            $dumpToken->isWord('true') || $dumpToken->isWord('false') => $this->call('boolean', strtolower($tokenCursor->next()->text)),
            default => $this->named($tokenCursor, $inner),
        };
    }

    /**
     * @param TokenCursor                    $tokenCursor At a name.
     * @param Closure(TokenCursor): CodeNode $inner       Reads a function's argument.
     *
     * @throws UnsupportedSql When the name is a function no `Expressions` method states, or not a name.
     *
     * @return CodeNode A column, or a function's call.
     */
    private function named(TokenCursor $tokenCursor, Closure $inner): CodeNode
    {
        $name = $tokenCursor->name();
        $next = $tokenCursor->peek();

        if (!$next instanceof DumpToken || !$next->isSymbol('(')) {
            return $this->call('column', $this->phpLiteral->string($name));
        }

        return $this->functionReader->read($name, $tokenCursor->group(), $inner);
    }

    /**
     * @param TokenCursor                    $tokenCursor At `ARRAY`.
     * @param Closure(TokenCursor): CodeNode $inner       Reads each item.
     *
     * @throws UnsupportedSql When an item is unsupported.
     *
     * @return CodeList The items.
     */
    private function array(TokenCursor $tokenCursor, Closure $inner): CodeList
    {
        $tokenCursor->expect('array', '[');
        $items = [];

        do {
            $items[] = $this->sum($tokenCursor, $inner);
        } while ($tokenCursor->accept(','));

        $tokenCursor->expect(']');

        return new CodeList($items);
    }

    /**
     * @param TokenCursor $tokenCursor After `::`.
     *
     * @throws UnsupportedSql When no type follows.
     *
     * @return string The cast's type: `text`, `character varying`, `text[]`.
     */
    private function castType(TokenCursor $tokenCursor): string
    {
        $type = strtolower($tokenCursor->name());
        $type = $type === 'character' && $tokenCursor->accept('varying')
            ? 'character varying'
            : $type;

        $next = $tokenCursor->peek();

        if ($next instanceof DumpToken && $next->isSymbol('(')) {
            $tokenCursor->group();
        }

        return $tokenCursor->accept('[', ']')
            ? $type . '[]'
            : $type;
    }

    /**
     * @param TokenCursor $tokenCursor At `-`.
     *
     * @throws UnsupportedSql When no number follows.
     *
     * @return CodeCall The negative number.
     */
    private function negative(TokenCursor $tokenCursor): CodeCall
    {
        $tokenCursor->expect('-');
        $dumpToken = $tokenCursor->next();

        return $dumpToken->dumpTokenKind === DumpTokenKind::Number
            ? $this->number('-' . $dumpToken->text)
            : throw new UnsupportedSql('a minus before ' . $dumpToken->text . ' has no Schema expression');
    }

    /**
     * @param string $text A number.
     *
     * @return CodeCall `integer(…)`, or `decimal('…')` for one with a point.
     */
    private function number(string $text): CodeCall
    {
        return str_contains($text, '.')
            ? $this->call('decimal', "'" . $text . "'")
            : $this->call('integer', preg_replace('/\\B(?=(\\d{3})+$)/', '_', $text) ?? $text);
    }

    /**
     * @param CodeNode $codeNode A term cast to numeric.
     *
     * @return CodeNode A whole number as `decimal('…')`, as Schema writes `(100000)::numeric`; any other term as it is.
     */
    private function decimal(CodeNode $codeNode): CodeNode
    {
        $number = $codeNode instanceof CodeCall && $codeNode->method === 'integer'
            ? $codeNode->valueArgument(0)
            : null;

        return $number instanceof CodeValue
            ? $this->call('decimal', "'" . str_replace('_', '', $number->php) . "'")
            : $codeNode;
    }

    /**
     * @param CodeNode $left  The left term.
     * @param string   $sign  `+`, `-`, `*` or `/`.
     * @param CodeNode $right The right term.
     *
     * @return CodeCall `calculate(…)`.
     */
    private function calculate(CodeNode $left, string $sign, CodeNode $right): CodeCall
    {
        return new CodeCall('calculate', [$left, new CodeValue('Arithmetic::' . self::ARITHMETIC[$sign]), $right]);
    }

    /**
     * @param CodeNode $left  The text so far.
     * @param CodeNode $right The text appended.
     *
     * @return CodeCall One `concat([…])` of every part.
     */
    private function concat(CodeNode $left, CodeNode $right): CodeCall
    {
        $joined = $left instanceof CodeCall && $left->method === 'concat'
            ? $left->listArgument(0)
            : null;
        $parts = $joined instanceof CodeList
            ? $joined->items
            : [$left];

        return new CodeCall('concat', [new CodeList([...$parts, $right])]);
    }

    /**
     * @param TokenCursor  $tokenCursor At a possible operator.
     * @param list<string> $operators   The operators of this level.
     *
     * @return string The operator, read; empty when none of them is next.
     */
    private function operator(TokenCursor $tokenCursor, array $operators): string
    {
        foreach ($operators as $operator) {
            if ($tokenCursor->accept($operator)) {
                return $operator;
            }
        }

        return '';
    }

    /**
     * @param string $method The `Expressions` method.
     * @param string $php    Its one argument, as PHP.
     *
     * @return CodeCall The call.
     */
    private function call(string $method, string $php): CodeCall
    {
        return new CodeCall($method, [new CodeValue($php)]);
    }
}
