<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\CodeCall;
use AIC\Workflow\Domain\Import\CodeList;
use AIC\Workflow\Domain\Import\CodeNode;
use AIC\Workflow\Domain\Import\CodeValue;
use AIC\Workflow\Exception\UnsupportedSql;
use Closure;

use function array_key_exists;
use function array_map;
use function count;
use function in_array;
use function strtolower;

/**
 * A function call of a deparsed Postgres expression (#318, #344), read into the `Expressions` method that states it.
 */
final readonly class FunctionReader
{
    /** Each function's `Expressions` method. */
    private const array METHODS = [
        'btrim' => 'trim',
        'char_length' => 'length',
        'coalesce' => 'coalesce',
        'jsonb_array_length' => 'jsonLength',
        'jsonb_typeof' => 'jsonKind',
        'length' => 'length',
        'lower' => 'lower',
        'num_nonnulls' => 'countPresent',
        'round' => 'round',
    ];

    /** The methods that take their terms as a list. */
    private const array LISTED = ['coalesce', 'countPresent'];

    /**
     * @param ConditionValues $conditionValues Makes a condition among the arguments a value.
     */
    public function __construct(
        private ConditionValues $conditionValues = new ConditionValues(),
    ) {
    }

    /**
     * @param string                         $name        The function.
     * @param TokenCursor                    $tokenCursor The arguments' tokens.
     * @param Closure(TokenCursor): CodeNode $inner       Reads an argument's whole expression.
     *
     * @throws UnsupportedSql When no `Expressions` method states the call.
     *
     * @return CodeCall The call.
     */
    public function read(string $name, TokenCursor $tokenCursor, Closure $inner): CodeCall
    {
        $function = strtolower($name);

        if (!array_key_exists($function, self::METHODS)) {
            throw new UnsupportedSql('the function ' . $name . '() has no Schema expression');
        }

        $method = self::METHODS[$function];
        $arguments = array_map(
            fn (TokenCursor $tokenCursor): CodeNode => $this->conditionValues->asTerm($inner($tokenCursor)),
            $tokenCursor->items(),
        );

        return in_array($method, self::LISTED, true)
            ? new CodeCall($method, [new CodeList($arguments)])
            : new CodeCall($method, $this->fitting($name, $method, $arguments));
    }

    /**
     * @param string         $name      The function.
     * @param string         $method    Its `Expressions` method.
     * @param list<CodeNode> $arguments Its arguments.
     *
     * @throws UnsupportedSql When the method takes another number of them, or the places of a rounding are no number.
     *
     * @return list<CodeNode> The method's arguments: the places of a rounding as PHP.
     */
    private function fitting(string $name, string $method, array $arguments): array
    {
        $first = $arguments[0] ?? null;
        $places = $method === 'round'
            ? $this->places($arguments[1] ?? null)
            : null;
        $expected = $places instanceof CodeValue
            ? 2
            : 1;

        if (!$first instanceof CodeNode || count($arguments) !== $expected) {
            throw new UnsupportedSql('the function ' . $name . '() with these arguments has no Schema expression');
        }

        return $places instanceof CodeValue
            ? [$first, $places]
            : [$first];
    }

    /**
     * @param CodeNode|null $codeNode A rounding's second argument.
     *
     * @throws UnsupportedSql When it is not a whole number.
     *
     * @return CodeValue The number, as PHP.
     */
    private function places(?CodeNode $codeNode): CodeValue
    {
        $places = $codeNode instanceof CodeCall && $codeNode->method === 'integer'
            ? $codeNode->valueArgument(0)
            : null;

        return $places ?? throw new UnsupportedSql('a rounding with no number of places has no Schema expression');
    }
}
