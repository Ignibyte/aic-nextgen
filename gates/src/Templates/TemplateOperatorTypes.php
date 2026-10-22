<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Ternary;

use function in_array;

/**
 * A template operator's static type, for the build (#97): a comparison or a
 * boolean operator is a bool, `.` a string, arithmetic an int or a float, a
 * ternary or a `match` any of its branches; TemplateLiteralTypes types the rest.
 */
final readonly class TemplateOperatorTypes
{
    /**
     * The binary operators that give a bool.
     */
    private const array BOOLEAN = [
        'Expr_BinaryOp_BooleanAnd',
        'Expr_BinaryOp_BooleanOr',
        'Expr_BinaryOp_Equal',
        'Expr_BinaryOp_Greater',
        'Expr_BinaryOp_GreaterOrEqual',
        'Expr_BinaryOp_Identical',
        'Expr_BinaryOp_NotEqual',
        'Expr_BinaryOp_NotIdentical',
        'Expr_BinaryOp_Smaller',
        'Expr_BinaryOp_SmallerOrEqual',
    ];

    /**
     * @param TemplateLiteralTypes $templateLiteralTypes Literals, constants, unary operators, `isset()` and `empty()`.
     */
    public function __construct(
        private TemplateLiteralTypes $templateLiteralTypes = new TemplateLiteralTypes(),
    ) {
    }

    /**
     * @param Expr                        $expr          An expression the grammar allows, other than a variable or a fetch.
     * @param array<string, TemplateType> $scope         The variables in scope.
     * @param TemplateTypes               $templateTypes Types the operands.
     *
     * @throws InvalidArgumentException When an operand has no usable type.
     *
     * @return TemplateType Its static type.
     */
    public function type(Expr $expr, array $scope, TemplateTypes $templateTypes): TemplateType
    {
        return match (true) {
            $expr instanceof BinaryOp => $this->binary($expr, $scope, $templateTypes),
            $expr instanceof Ternary => $this->ternary($expr, $scope, $templateTypes),
            $expr instanceof Match_ => $this->match($expr, $scope, $templateTypes),
            default => $this->templateLiteralTypes->type($expr, $scope, $templateTypes),
        };
    }

    /**
     * @param BinaryOp                    $binaryOp      A binary operator the grammar allows.
     * @param array<string, TemplateType> $scope         The variables in scope.
     * @param TemplateTypes               $templateTypes Types the operands.
     *
     * @throws InvalidArgumentException When an operand has no usable type.
     *
     * @return TemplateType A bool, a string for `.`, an int or float for arithmetic, an int for `<=>`.
     */
    private function binary(BinaryOp $binaryOp, array $scope, TemplateTypes $templateTypes): TemplateType
    {
        $templateTypes->type($binaryOp->left, $scope);
        $templateTypes->type($binaryOp->right, $scope);

        return match (true) {
            in_array($binaryOp->getType(), self::BOOLEAN, true) => new TemplateType([TemplateTypeKind::Bool]),
            $binaryOp instanceof Concat => new TemplateType([TemplateTypeKind::String]),
            $binaryOp->getType() === 'Expr_BinaryOp_Spaceship' => new TemplateType([TemplateTypeKind::Int]),
            default => new TemplateType([TemplateTypeKind::Int, TemplateTypeKind::Float]),
        };
    }

    /**
     * @param Ternary                     $ternary       `a ? b : c` or `a ?: c`.
     * @param array<string, TemplateType> $scope         The variables in scope.
     * @param TemplateTypes               $templateTypes Types the operands.
     *
     * @throws InvalidArgumentException When `a` is not a bool, or a branch has no usable type.
     *
     * @return TemplateType Either branch.
     */
    private function ternary(Ternary $ternary, array $scope, TemplateTypes $templateTypes): TemplateType
    {
        $templateTypes->condition($ternary->cond, $scope);
        $then = $ternary->if instanceof Expr
            ? $templateTypes->type($ternary->if, $scope)
            : new TemplateType([TemplateTypeKind::Bool]);

        return $then->join($templateTypes->type($ternary->else, $scope));
    }

    /**
     * @param Match_                      $match         A `match`.
     * @param array<string, TemplateType> $scope         The variables in scope.
     * @param TemplateTypes               $templateTypes Types the operands.
     *
     * @throws InvalidArgumentException When an arm has no usable type.
     *
     * @return TemplateType Any arm's body.
     */
    private function match(Match_ $match, array $scope, TemplateTypes $templateTypes): TemplateType
    {
        $templateTypes->type($match->cond, $scope);
        $type = new TemplateType([]);

        foreach ($match->arms as $matchArm) {
            foreach ($matchArm->conds ?? [] as $cond) {
                $templateTypes->type($cond, $scope);
            }

            $type = $type->join($templateTypes->type($matchArm->body, $scope));
        }

        return $type;
    }
}
