<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;

/**
 * A template literal's static type, for the build (#97): a string, a number,
 * `true`, `false` or `null`, `[]`, a unary sign over its operand; `isset()`,
 * `empty()` and `!` are bools, and `isset()` refuses an operand that cannot
 * be null.
 */
final readonly class TemplateLiteralTypes
{
    /**
     * @param Expr                        $expr          A literal, a constant, a unary operator, `isset()` or `empty()`.
     * @param array<string, TemplateType> $scope         The variables in scope.
     * @param TemplateTypes               $templateTypes Types the operands.
     *
     * @throws InvalidArgumentException When an operand has no usable type.
     *
     * @return TemplateType Its type.
     */
    public function type(Expr $expr, array $scope, TemplateTypes $templateTypes): TemplateType
    {
        if ($expr instanceof Isset_) {
            foreach ($expr->vars as $var) {
                $templateTypes->nullable($var, $scope);
            }
        }

        return match (true) {
            $expr instanceof String_, $expr instanceof InterpolatedString => new TemplateType([TemplateTypeKind::String]),
            $expr instanceof Int_ => new TemplateType([TemplateTypeKind::Int]),
            $expr instanceof Float_ => new TemplateType([TemplateTypeKind::Float]),
            $expr instanceof UnaryMinus, $expr instanceof UnaryPlus => $templateTypes->type($expr->expr, $scope),
            $expr instanceof ConstFetch => new TemplateType([
                $expr->name->toLowerString() === 'null' ? TemplateTypeKind::Null : TemplateTypeKind::Bool,
            ]),
            $expr instanceof Array_ => new TemplateType([TemplateTypeKind::List], '', new TemplateType([TemplateTypeKind::Unknown])),
            default => new TemplateType([TemplateTypeKind::Bool]),
        };
    }
}
