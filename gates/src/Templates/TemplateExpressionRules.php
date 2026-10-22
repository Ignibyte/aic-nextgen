<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateScope;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

use function in_array;
use function is_string;
use function ltrim;

/**
 * The checks on a template's expressions (#96): a variable in scope, a
 * property read by name, a function only from the (empty) allow-list, the
 * constants `true`, `false` and `null`, an enum case or class constant of a
 * named class (Chad, 2026-10-21; `::class` stays out), assignment only in a
 * `for` header, and plain array items.
 */
final readonly class TemplateExpressionRules
{
    /**
     * The attribute the walk sets on a `for` header's expressions, where assignment is allowed.
     */
    public const string FOR_HEADER = 'templateForHeader';

    /**
     * Where the function allow-list lives, for the refusal.
     */
    private const string ALLOW_LIST = TemplateGrammar::class . '::functions()';

    /**
     * @param TemplateGrammar $templateGrammar The functions and superglobals.
     */
    public function __construct(
        private TemplateGrammar $templateGrammar = new TemplateGrammar(),
    ) {
    }

    /**
     * @param Node          $node          A checked expression.
     * @param TemplateScope $templateScope The variables in scope.
     *
     * @return array{TemplateRefusal, string}|null Its refusal and detail; null when it passes.
     */
    public function check(Node $node, TemplateScope $templateScope): ?array
    {
        return match (true) {
            $node instanceof Variable => $this->variable($node, $templateScope),
            $node instanceof FuncCall => $this->call($node),
            $node instanceof ClassConstFetch => $this->classConstant($node),
            $node instanceof PropertyFetch, $node instanceof NullsafePropertyFetch, $node instanceof ConstFetch => $this->read($node),
            $node instanceof ArrayItem, $node instanceof ArrayDimFetch => $this->arrayPart($node),
            default => $this->assignment($node),
        };
    }

    /**
     * @param PropertyFetch|NullsafePropertyFetch|ConstFetch $node A property read or a constant.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless the property is named, or the constant is
     *                                             true, false or null.
     */
    private function read(PropertyFetch|NullsafePropertyFetch|ConstFetch $node): ?array
    {
        if ($node instanceof ConstFetch) {
            return in_array($node->name->toLowerString(), ['true', 'false', 'null'], true)
                ? null
                : [TemplateRefusal::NotInGrammar, 'the constants are true, false and null; read others as a class constant'];
        }

        return $node->name instanceof Identifier
            ? null
            : [TemplateRefusal::NotInGrammar, 'a property is read by its name'];
    }

    /**
     * @param ArrayItem|ArrayDimFetch $node An array literal's item, or an array read.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless the item is a plain value, or the read names its key.
     */
    private function arrayPart(ArrayItem|ArrayDimFetch $node): ?array
    {
        if ($node instanceof ArrayItem) {
            return $node->byRef || $node->unpack
                ? [TemplateRefusal::NotInGrammar, 'an array item is a plain value']
                : null;
        }

        return $node->dim === null
            ? [TemplateRefusal::NotInGrammar, 'an array read names its key']
            : null;
    }

    /**
     * @param Variable      $variable      A variable.
     * @param TemplateScope $templateScope The variables in scope.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless it is the component or a loop variable.
     */
    private function variable(Variable $variable, TemplateScope $templateScope): ?array
    {
        if (!is_string($variable->name)) {
            return [TemplateRefusal::VariableVariable, 'a variable is named, never computed'];
        }

        if (in_array($variable->name, $this->templateGrammar->superglobals(), true)) {
            return [TemplateRefusal::Superglobal, 'a template reads only its component; the component passes the value in'];
        }

        return $templateScope->has($variable->name)
            ? null
            : [TemplateRefusal::NotInScope, '$' . $variable->name . ' is neither the component variable nor a loop variable'];
    }

    /**
     * @param FuncCall $funcCall A function call.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless the function is on the allow-list.
     */
    private function call(FuncCall $funcCall): ?array
    {
        if (!$funcCall->name instanceof Name) {
            return [TemplateRefusal::FunctionCall, 'a template calls no computed function'];
        }

        $name = ltrim($funcCall->name->toLowerString(), '\\');

        return in_array($name, $this->templateGrammar->functions(), true)
            ? null
            : [TemplateRefusal::FunctionCall, $name . '() is not in the template function allow-list (' . self::ALLOW_LIST . ', empty)'];
    }

    /**
     * @param ClassConstFetch $classConstFetch A `X::Y` read.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless it reads a named class's constant or enum case.
     */
    private function classConstant(ClassConstFetch $classConstFetch): ?array
    {
        $class = $classConstFetch->class;
        $constant = $classConstFetch->name;

        if (
            !$class instanceof Name
            || in_array($class->toLowerString(), ['self', 'static', 'parent'], true)
            || !$constant instanceof Identifier
        ) {
            return [TemplateRefusal::NotInGrammar, 'a class constant is read from a named class: Status::Sold'];
        }

        return $constant->toLowerString() === 'class'
            ? [TemplateRefusal::NotInGrammar, '::class is outside the grammar (Chad, 2026-10-21); a template names no class']
            : null;
    }

    /**
     * @param Node $node An assignment, compound assignment, increment or decrement.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless it sits in a `for` header.
     */
    private function assignment(Node $node): ?array
    {
        return $node->getAttribute(self::FOR_HEADER) === true
            ? null
            : [TemplateRefusal::Assignment, "a template assigns nothing, but a for loop's own counter"];
    }
}
