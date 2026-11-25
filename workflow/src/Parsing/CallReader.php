<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

/**
 * What a method call names and is handed (#338): its method, an argument, and
 * an argument that is a string literal.
 */
final readonly class CallReader
{
    /**
     * @param MethodCall $methodCall A call.
     *
     * @return string Its method's name; empty for a computed one.
     */
    public function called(MethodCall $methodCall): string
    {
        return $methodCall->name instanceof Identifier
            ? $methodCall->name->toString()
            : '';
    }

    /**
     * @param MethodCall $methodCall A call.
     * @param int        $index      An argument's place.
     *
     * @return Expr|null The argument; null when there is none or it is spread.
     */
    public function argument(MethodCall $methodCall, int $index): ?Expr
    {
        $arg = $methodCall->args[$index] ?? null;

        return $arg instanceof Arg
            ? $arg->value
            : null;
    }

    /**
     * @param MethodCall $methodCall A call.
     * @param int        $index      An argument's place.
     *
     * @return string The argument when it is a string literal; empty otherwise.
     */
    public function text(MethodCall $methodCall, int $index): string
    {
        $value = $this->argument($methodCall, $index);

        return $value instanceof String_
            ? $value->value
            : '';
    }
}
