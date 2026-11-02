<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use Override;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeVisitorAbstract;

/**
 * The declarations of a file (#106, droost-engine's `GraphVisitor`, class
 * half): class-likes, functions and methods as symbols, and their
 * `extends`, `implements` and `uses` (a trait) edges. It keeps the sink's
 * place in the walk, so it runs before the reference visitor.
 */
final class DeclarationVisitor extends NodeVisitorAbstract
{
    /**
     * @param SymbolSink $symbolSink Where the walk's findings go.
     * @param NameReader $nameReader Reads names.
     */
    public function __construct(
        private readonly SymbolSink $symbolSink,
        private readonly NameReader $nameReader,
    ) {
    }

    /**
     * @param Node $node A node.
     */
    #[Override]
    public function enterNode(Node $node): null
    {
        return match (true) {
            $node instanceof ClassLike => $this->classLike($node),
            $node instanceof ClassMethod => $this->classMethod($node),
            $node instanceof Function_ => $this->function($node),
            $node instanceof TraitUse => $this->targets($node->traits, 'uses'),
            default => null,
        };
    }

    /**
     * @param Node $node A node the walk leaves.
     */
    #[Override]
    public function leaveNode(Node $node): null
    {
        if ($node instanceof ClassLike) {
            $this->symbolSink->leaveClass();
        }

        if ($node instanceof ClassMethod) {
            $this->symbolSink->method('');
        }

        return null;
    }

    /**
     * @param ClassLike $classLike A class, interface, trait or enum.
     */
    private function classLike(ClassLike $classLike): null
    {
        $name = $this->nameReader->declared($classLike->namespacedName);
        $this->symbolSink->enterClass($name);
        $this->symbolSink->symbol($name, $this->kind($classLike), $classLike->getStartLine());

        return $name === ''
            ? null
            : $this->parents($classLike);
    }

    /**
     * @param ClassLike $classLike A named class-like.
     */
    private function parents(ClassLike $classLike): null
    {
        $extends = match (true) {
            $classLike instanceof Class_ => $classLike->extends instanceof Name ? [$classLike->extends] : [],
            $classLike instanceof Interface_ => $classLike->extends,
            default => [],
        };
        $this->targets($extends, 'extends');

        return $this->targets($classLike instanceof Class_ || $classLike instanceof Enum_ ? $classLike->implements : [], 'implements');
    }

    /**
     * @param ClassLike $classLike A class-like.
     *
     * @return string Its kind.
     */
    private function kind(ClassLike $classLike): string
    {
        return match (true) {
            $classLike instanceof Class_ => 'class',
            $classLike instanceof Interface_ => 'interface',
            $classLike instanceof Enum_ => 'enum',
            default => 'trait',
        };
    }

    /**
     * @param ClassMethod $classMethod A method.
     */
    private function classMethod(ClassMethod $classMethod): null
    {
        $class = $this->symbolSink->currentClass();
        $method = $class === ''
            ? ''
            : $class . '::' . $classMethod->name->toString();
        $this->symbolSink->method($method);
        $this->symbolSink->symbol(
            $method,
            'method',
            $classMethod->getStartLine(),
            $this->nameReader->typeName($classMethod->returnType, $class),
            $classMethod->isPublic(),
        );

        return null;
    }

    /**
     * @param Function_ $function A function.
     */
    private function function(Function_ $function): null
    {
        $this->symbolSink->symbol($this->nameReader->declared($function->namespacedName), 'function', $function->getStartLine());

        return null;
    }

    /**
     * @param array<Name> $names Names a class-like or a trait use names.
     * @param string      $kind  The edge they make.
     */
    private function targets(array $names, string $kind): null
    {
        foreach ($names as $name) {
            $this->symbolSink->edge($this->nameReader->className($name, $this->symbolSink->currentClass()), $kind);
        }

        return null;
    }
}
