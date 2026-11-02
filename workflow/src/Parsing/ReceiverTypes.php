<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;

use function array_pop;
use function end;
use function is_string;

/**
 * What a walk knows of a receiver's class (#232), from declarations only:
 * each class-like's typed properties and promoted parameters, each
 * function's typed parameters (closures' added to the method's), and a
 * variable assigned a `new`. Nothing is inferred across other assignments,
 * so a receiver it cannot type reads as empty. It changes as the walk goes,
 * so it is not readonly.
 */
final class ReceiverTypes
{
    /**
     * @var list<array<string, string>> Each class-like's property types by name, innermost last.
     */
    private array $properties = [];

    /**
     * @var array<string, string> The current method's variable types by name.
     */
    private array $variables = [];

    /**
     * @param NameReader $nameReader Reads names and types.
     */
    public function __construct(
        private readonly NameReader $nameReader,
    ) {
    }

    /**
     * @param ClassLike $classLike A class-like the walk enters.
     * @param string    $class     Its name; empty for an anonymous one.
     */
    public function enterClass(ClassLike $classLike, string $class): void
    {
        $types = [];

        foreach ($classLike->getProperties() as $property) {
            $types = [...$types, ...$this->declared($property, $class)];
        }

        $constructor = $classLike->getMethod('__construct');

        $params = $constructor instanceof ClassMethod
            ? $constructor->params
            : [];

        foreach ($params as $param) {
            $types = $param->isPromoted()
                ? [...$types, ...$this->parameter($param, $class)]
                : $types;
        }

        $this->properties[] = $types;
    }

    /**
     * The walk leaves a class-like.
     */
    public function leaveClass(): void
    {
        array_pop($this->properties);
    }

    /**
     * @param FunctionLike $functionLike A method, function, closure or arrow function the walk enters: a method or
     *                                   function starts a scope, a closure's parameters join the one it sits in.
     * @param string       $class        The class-like it sits in.
     */
    public function enterFunction(FunctionLike $functionLike, string $class): void
    {
        $variables = $functionLike instanceof Closure || $functionLike instanceof ArrowFunction
            ? $this->variables
            : [];

        foreach ($functionLike->getParams() as $param) {
            $variables = [...$variables, ...$this->parameter($param, $class)];
        }

        $this->variables = $variables;
    }

    /**
     * @param Assign $assign An assignment.
     * @param string $class  The class-like it sits in.
     */
    public function assign(Assign $assign, string $class): void
    {
        $variable = $assign->var;
        $name = $variable instanceof Variable
            ? $variable->name
            : null;

        if (!is_string($name) || !$assign->expr instanceof New_) {
            return;
        }

        $this->variables[$name] = $this->type($assign->expr, $class);
    }

    /**
     * @param Expr   $expr  A receiver.
     * @param string $class The class-like it sits in.
     *
     * @return string Its class: `$this`, a typed variable, a typed property on `$this`, or a `new`; empty when unknown.
     */
    public function type(Expr $expr, string $class): string
    {
        return match (true) {
            $expr instanceof Variable => $this->variable($expr, $class),
            $expr instanceof PropertyFetch => $this->property($expr),
            $expr instanceof New_ => $expr->class instanceof Name ? $this->nameReader->className($expr->class, $class) : '',
            default => '',
        };
    }

    /**
     * @param Variable $variable A variable.
     * @param string   $class    The class-like it sits in.
     *
     * @return string `$this` as the class, or the variable's known class; empty when unknown.
     */
    private function variable(Variable $variable, string $class): string
    {
        if (!is_string($variable->name)) {
            return '';
        }

        return $variable->name === 'this'
            ? $class
            : $this->variables[$variable->name] ?? '';
    }

    /**
     * @param PropertyFetch $propertyFetch A property read.
     *
     * @return string The declared class of a property on `$this`; empty otherwise.
     */
    private function property(PropertyFetch $propertyFetch): string
    {
        $own = $propertyFetch->var instanceof Variable && $propertyFetch->var->name === 'this';
        $types = end($this->properties);

        return $own && $propertyFetch->name instanceof Identifier && $types !== false
            ? $types[$propertyFetch->name->toString()] ?? ''
            : '';
    }

    /**
     * @param Property $property A property declaration.
     * @param string   $class    The class-like it sits in.
     *
     * @return array<string, string> Each of its names with its class, when it names one.
     */
    private function declared(Property $property, string $class): array
    {
        $type = $this->nameReader->typeName($property->type, $class);
        $types = [];

        foreach ($property->props as $propertyItem) {
            $types = $type === ''
                ? $types
                : [...$types, $propertyItem->name->toString() => $type];
        }

        return $types;
    }

    /**
     * @param Param  $param A parameter.
     * @param string $class The class-like it sits in.
     *
     * @return array<string, string> Its name with its class, when it has a name and names one.
     */
    private function parameter(Param $param, string $class): array
    {
        $type = $this->nameReader->typeName($param->type, $class);

        return $type !== '' && $param->var instanceof Variable && is_string($param->var->name)
            ? [$param->var->name => $type]
            : [];
    }
}
