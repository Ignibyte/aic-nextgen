<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeVisitorAbstract;

use function in_array;

/**
 * The references of a file (#106, droost-engine's `GraphVisitor`, call
 * half): `instantiates` (`new`), `calls` (on `$this`, `self::`, `static::`)
 * and `refers` (every other class name the resolver qualified: types,
 * `::class`, `instanceof`, `catch`, attributes, static members), from the
 * method or class the walk is in. Calls on other objects stay unrecorded, as
 * Droost decided.
 */
final class ReferenceVisitor extends NodeVisitorAbstract
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
            $node instanceof New_ => $this->instantiation($node),
            $node instanceof MethodCall => $this->ownCall($node->var instanceof Variable && $node->var->name === 'this', $node->name),
            $node instanceof StaticCall => $this->ownCall($this->ownClass($node->class), $node->name),
            $node instanceof FullyQualified => $this->refers($node),
            default => null,
        };
    }

    /**
     * @param New_ $new A `new`.
     */
    private function instantiation(New_ $new): null
    {
        if ($new->class instanceof Name) {
            $this->symbolSink->edge($this->nameReader->className($new->class, $this->symbolSink->currentClass()), 'instantiates');
        }

        return null;
    }

    /**
     * @param bool $own  Whether the call is on the class itself.
     * @param Node $name The method called: an identifier, or an expression that is not tracked.
     */
    private function ownCall(bool $own, Node $name): null
    {
        $class = $this->symbolSink->currentClass();

        if ($own && $class !== '' && $name instanceof Identifier) {
            $this->symbolSink->edge($class . '::' . $name->toString(), 'calls');
        }

        return null;
    }

    /**
     * @param Node $class A static call's class.
     *
     * @return bool Whether it is `self` or `static`.
     */
    private function ownClass(Node $class): bool
    {
        return $class instanceof Name && in_array($class->toLowerString(), ['self', 'static'], true);
    }

    /**
     * @param FullyQualified $fullyQualified A class name the resolver qualified.
     */
    private function refers(FullyQualified $fullyQualified): null
    {
        $parent = [$fullyQualified->getAttribute('parent')];
        $declared = $parent[0] instanceof ClassLike || $parent[0] instanceof TraitUse || $parent[0] instanceof New_;

        if (!$declared && !$parent[0] instanceof FuncCall && !$parent[0] instanceof ConstFetch) {
            $this->symbolSink->edge($fullyQualified->toString(), 'refers');
        }

        return null;
    }
}
