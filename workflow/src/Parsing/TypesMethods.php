<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Exception\WorkflowRefusal;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;

use function str_ends_with;

/**
 * The two method shapes of a module's types class (#328): a private `<entity>With()` returning
 * `new EntityType('<table>', …)`, and a public `<entity>()` returning `$this-><entity>With([…])`, whose list holds its
 * relations.
 */
final readonly class TypesMethods
{
    /**
     * The entity domain's namespace, from which the entity type class is named.
     */
    private const string DOMAIN = 'AIC\\Entity\\Domain';

    /**
     * @param ClassMethod $classMethod A method.
     *
     * @return string The table, when it is a private `<entity>With()` returning `new EntityType('<table>', …)`; empty
     *                otherwise.
     */
    public function table(ClassMethod $classMethod): string
    {
        $new = $this->entityType($classMethod);
        $argument = $new instanceof New_
            ? $new->args[0] ?? null
            : null;

        return $argument instanceof Arg && $argument->value instanceof String_
            ? $argument->value->value
            : '';
    }

    /**
     * @param Class_ $class    The types class.
     * @param string $accessor A public accessor.
     * @param string $file     Its path, for a refusal.
     *
     * @throws WorkflowRefusal When the accessor does not return `$this-><accessor>With([…])`.
     *
     * @return Array_ The list of its relations.
     */
    public function relations(Class_ $class, string $accessor, string $file): Array_
    {
        $classMethod = $class->getMethod($accessor);
        $call = $classMethod instanceof ClassMethod
            ? self::returned($classMethod)
            : null;
        $argument = self::ownCall($call, $accessor . 'With')
            ? $call->args[0] ?? null
            : null;

        if (!$argument instanceof Arg || !$argument->value instanceof Array_) {
            throw new WorkflowRefusal(
                $file . ': ' . $accessor . '() does not return $this->' . $accessor . 'With([…]); add the relation by hand.',
            );
        }

        return $argument->value;
    }

    /**
     * @param ClassMethod $classMethod A method.
     *
     * @return New_|null The `new EntityType(…)` it returns, when it is a private `<entity>With()`; null otherwise.
     */
    private function entityType(ClassMethod $classMethod): ?New_
    {
        $new = $classMethod->isPrivate() && str_ends_with($classMethod->name->toString(), 'With')
            ? self::returned($classMethod)
            : null;

        return $new instanceof New_ && $new->class instanceof Name && $new->class->toString() === self::DOMAIN . '\\EntityType'
            ? $new
            : null;
    }

    /**
     * @param ClassMethod $classMethod A method.
     *
     * @return Expr|null What its first statement returns; null when it starts otherwise.
     */
    private static function returned(ClassMethod $classMethod): ?Expr
    {
        $return = $classMethod->stmts[0] ?? null;

        return $return instanceof Return_
            ? $return->expr
            : null;
    }

    /**
     * @param Expr|null $expr An expression.
     * @param string    $name A method's name.
     *
     * @phpstan-assert-if-true MethodCall $expr
     *
     * @return bool Whether it calls that method on `$this`.
     */
    private static function ownCall(?Expr $expr, string $name): bool
    {
        return $expr instanceof MethodCall && $expr->var instanceof Variable && $expr->var->name === 'this'
            && $expr->name instanceof Identifier && $expr->name->toString() === $name;
    }
}
