<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use Gates\Domain\TemplateTypeKind;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;

use function is_string;
use function lcfirst;

/**
 * A loop's variables for the build (#97): a `foreach` loops over a typed list,
 * its value takes the list's element type and its key is an int, and a `for`'s
 * counters are ints, each a typed parameter of the parts its body compiles to. A value of a class is named for
 * it, as the typing line names the component (`KeyedComponent
 * $keyedComponent`), the name Rector gives a parameter of that type.
 */
final readonly class TemplateLoopTypes
{
    /**
     * @param Foreach_         $foreach          The loop.
     * @param TemplateType     $templateType     The type of what it loops over.
     * @param TemplateLowering $templateLowering Imports the element's class.
     *
     * @throws InvalidArgumentException When it does not loop over a typed list, or its value is misnamed.
     *
     * @return array{array<string, TemplateType>, list<string>} Its variables' types by name, and its parameters,
     *                                                          `Type $var`, key first.
     */
    public function foreach(Foreach_ $foreach, TemplateType $templateType, TemplateLowering $templateLowering): array
    {
        $element = $templateType->only(TemplateTypeKind::List)
            ? $templateType->element
            : null;
        $value = $this->variableName($foreach->valueVar);

        if (!$element instanceof TemplateType || !is_string($value)) {
            throw new InvalidArgumentException(TemplateRefusal::LoopType->value . ': foreach loops over a property typed list<T>');
        }

        $bound = [];
        $parameters = [];
        $key = $this->variableName($foreach->keyVar);

        if (is_string($key)) {
            $bound[$key] = new TemplateType([TemplateTypeKind::Int]);
            $parameters[] = 'int $' . $key;
        }

        $bound[$value] = $element;
        $parameters[] = $this->parameterType($element, $value, $templateLowering) . ' $' . $value;

        return [$bound, $parameters];
    }

    /**
     * @param For_ $for A `for`.
     *
     * @return array<string, TemplateType> The counters its initialiser assigns, each an int.
     */
    public function counters(For_ $for): array
    {
        $bound = [];

        foreach ($for->init as $init) {
            $variable = $init instanceof Assign
                ? $init->var
                : null;
            $name = $this->variableName($variable);

            if (!is_string($name)) {
                continue;
            }

            $bound[$name] = new TemplateType([TemplateTypeKind::Int]);
        }

        return $bound;
    }

    /**
     * @param TemplateType     $templateType     A list's element type.
     * @param string           $name             The loop's value variable.
     * @param TemplateLowering $templateLowering Imports a class.
     *
     * @throws InvalidArgumentException When it is neither one class nor a scalar, or a class's variable is misnamed.
     *
     * @return string Its parameter type.
     */
    private function parameterType(TemplateType $templateType, string $name, TemplateLowering $templateLowering): string
    {
        if ($templateType->only(TemplateTypeKind::Object) && $templateType->class !== '') {
            return $this->classParameter($templateType->class, $name, $templateLowering);
        }

        return match (true) {
            $templateType->only(TemplateTypeKind::Bool) => 'bool',
            $templateType->only(TemplateTypeKind::Float) => 'float',
            $templateType->only(TemplateTypeKind::Int) => 'int',
            $templateType->only(TemplateTypeKind::String) => 'string',
            default => throw new InvalidArgumentException(
                TemplateRefusal::LoopType->value . ": a list's element is one class, or a string, int, float or bool",
            ),
        };
    }

    /**
     * @param string           $class            The element's class.
     * @param string           $name             The loop's value variable.
     * @param TemplateLowering $templateLowering Imports the class.
     *
     * @throws InvalidArgumentException When the variable is not named for the class.
     *
     * @return string The class's short name, its parameter type.
     */
    private function classParameter(string $class, string $name, TemplateLowering $templateLowering): string
    {
        $short = $templateLowering->import($class);

        if ($name !== lcfirst($short)) {
            throw new InvalidArgumentException(
                TemplateRefusal::LoopType->value . ': a ' . $short . ' is named $' . lcfirst($short) . ', not $' . $name,
            );
        }

        return $short;
    }

    /**
     * @param Expr|null $expr A possible named loop variable.
     *
     * @return string|null Its checked name; null for computed or absent variables.
     */
    private function variableName(?Expr $expr): ?string
    {
        $name = $expr instanceof Variable
            ? $expr->name
            : null;

        return is_string($name)
            ? $name
            : null;
    }
}
