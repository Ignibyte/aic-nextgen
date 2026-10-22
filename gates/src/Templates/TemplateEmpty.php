<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * Lowers `empty(x)` by x's static type (#97): the generated class may not
 * call `empty()`, and a comparison with the type's empty value says the same.
 */
final readonly class TemplateEmpty
{
    /**
     * @param TemplateTypes $templateTypes Types `empty()`'s operand.
     */
    public function __construct(
        private TemplateTypes $templateTypes = new TemplateTypes(),
    ) {
    }

    /**
     * @param Expr                        $expr  `empty()`'s operand.
     * @param array<string, TemplateType> $scope The variables in scope.
     *
     * @throws InvalidArgumentException When its type has no empty value to compare with.
     *
     * @return Expr The comparison with the empty value of its type: `=== []`, `=== ''`, `=== 0`, `=== 0.0`, or `!x`.
     */
    public function lower(Expr $expr, array $scope): Expr
    {
        $type = $this->templateTypes->type($expr, $scope);

        return match (true) {
            $type->only(TemplateTypeKind::List) => new Identical($expr, new Array_([])),
            $type->only(TemplateTypeKind::String) => new Identical($expr, new String_('')),
            $type->only(TemplateTypeKind::Int) => new Identical($expr, new Int_(0)),
            $type->only(TemplateTypeKind::Float) => new Identical($expr, new Float_(0.0)),
            $type->only(TemplateTypeKind::Bool) => new BooleanNot($expr),
            default => throw new InvalidArgumentException(
                TemplateRefusal::ConditionType->value . ': empty() takes a list, a string, an int, a float or a bool',
            ),
        };
    }
}
