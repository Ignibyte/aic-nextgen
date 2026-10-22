<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;

use function is_string;

/**
 * A template expression's static type, for the build (#97): the component
 * and loop variables from the scope, a property from its declaration, a
 * constant or enum case from its class; TemplateOperatorTypes types the
 * operators and literals. A
 * null test on a value that cannot be null is refused: the generated class
 * would not pass PHPStan, and the template has a type error to fix.
 */
final readonly class TemplateTypes
{
    /**
     * @param TemplateClassTypes    $templateClassTypes    Reflection over the template's classes.
     * @param TemplateOperatorTypes $templateOperatorTypes Operators and literals.
     */
    public function __construct(
        private TemplateClassTypes $templateClassTypes = new TemplateClassTypes(),
        private TemplateOperatorTypes $templateOperatorTypes = new TemplateOperatorTypes(),
    ) {
    }

    /**
     * @param Expr                        $expr  An expression the grammar allows.
     * @param array<string, TemplateType> $scope The variables in scope, by name.
     *
     * @throws InvalidArgumentException When a part of it has no type the build can use (`category: detail`).
     *
     * @return TemplateType Its static type.
     */
    public function type(Expr $expr, array $scope): TemplateType
    {
        return match (true) {
            $expr instanceof Variable => $scope[is_string($expr->name) ? $expr->name : ''] ?? new TemplateType([TemplateTypeKind::Unknown]),
            $expr instanceof PropertyFetch, $expr instanceof NullsafePropertyFetch => $this->property($expr, $scope),
            $expr instanceof ArrayDimFetch => $this->element($expr, $scope),
            $expr instanceof ClassConstFetch => $this->constant($expr),
            $expr instanceof Coalesce => $this->coalesce($expr, $scope),
            default => $this->templateOperatorTypes->type($expr, $scope, $this),
        };
    }

    /**
     * @param Expr                        $expr  A condition.
     * @param array<string, TemplateType> $scope The variables in scope.
     *
     * @throws InvalidArgumentException When it is not a bool (`condition type`).
     */
    public function condition(Expr $expr, array $scope): void
    {
        if (!$this->type($expr, $scope)->only(TemplateTypeKind::Bool)) {
            throw new InvalidArgumentException(
                TemplateRefusal::ConditionType->value . ": a condition is a bool; compare it: === [], === '', === null",
            );
        }
    }

    /**
     * @param Expr                        $expr  A null test's operand (`isset`, `??`, `?->`).
     * @param array<string, TemplateType> $scope The variables in scope.
     *
     * @throws InvalidArgumentException When it cannot be null.
     */
    public function nullable(Expr $expr, array $scope): void
    {
        if (!$this->type($expr, $scope)->may(TemplateTypeKind::Null)) {
            throw new InvalidArgumentException(TemplateRefusal::NotCompilable->value . ': this value cannot be null; drop the null test');
        }
    }

    /**
     * @param PropertyFetch|NullsafePropertyFetch $fetch A property read.
     * @param array<string, TemplateType>         $scope The variables in scope.
     *
     * @throws InvalidArgumentException When the object or the property has no usable type.
     *
     * @return TemplateType The property's type; nullable after `?->`.
     */
    private function property(PropertyFetch|NullsafePropertyFetch $fetch, array $scope): TemplateType
    {
        if ($fetch instanceof NullsafePropertyFetch) {
            $this->nullable($fetch->var, $scope);
        }

        $templateType = $this->type($fetch->var, $scope);
        $name = $fetch->name instanceof Identifier
            ? $fetch->name->toString()
            : '';
        $type = $this->templateClassTypes->property($templateType->withoutNull(), $name);

        return $fetch instanceof NullsafePropertyFetch
            ? $type->join(new TemplateType([TemplateTypeKind::Null]))
            : $type;
    }

    /**
     * @param ArrayDimFetch               $arrayDimFetch A list read: `$c->items[0]`.
     * @param array<string, TemplateType> $scope         The variables in scope.
     *
     * @throws InvalidArgumentException When it does not read a typed list.
     *
     * @return TemplateType The element type, nullable: the key may be missing.
     */
    private function element(ArrayDimFetch $arrayDimFetch, array $scope): TemplateType
    {
        $templateType = $this->type($arrayDimFetch->var, $scope);

        if (!$templateType->only(TemplateTypeKind::List) || !$templateType->element instanceof TemplateType) {
            throw new InvalidArgumentException(TemplateRefusal::Property->value . ': [...] reads a typed list');
        }

        return $templateType->element->join(new TemplateType([TemplateTypeKind::Null]));
    }

    /**
     * @param ClassConstFetch $classConstFetch `\Fqcn::NAME`.
     *
     * @throws InvalidArgumentException When the class is not fully qualified, or it or the constant is unknown.
     *
     * @return TemplateType The case's enum, or the constant's value type.
     */
    private function constant(ClassConstFetch $classConstFetch): TemplateType
    {
        $class = $classConstFetch->class instanceof FullyQualified
            ? $classConstFetch->class->toString()
            : throw new InvalidArgumentException(
                TemplateRefusal::ClassName->value . ': write the class fully qualified, \\Vendor\\Name::CONSTANT',
            );
        $name = $classConstFetch->name instanceof Identifier
            ? $classConstFetch->name->toString()
            : '';

        return $this->templateClassTypes->constant($class, $name);
    }

    /**
     * @param Coalesce                    $coalesce `a ?? b`.
     * @param array<string, TemplateType> $scope    The variables in scope.
     *
     * @throws InvalidArgumentException When `a` cannot be null, or a side has no usable type.
     *
     * @return TemplateType `a` without null, or `b`.
     */
    private function coalesce(Coalesce $coalesce, array $scope): TemplateType
    {
        $this->nullable($coalesce->left, $scope);

        return $this->type($coalesce->left, $scope)->withoutNull()->join($this->type($coalesce->right, $scope));
    }
}
