<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeVisitorAbstract;

use function array_unshift;
use function count;
use function implode;

/**
 * The call chains of a file (#232): each outermost method or static call
 * whose first receiver's class the walk can tell (`ReceiverTypes`), as one
 * `chains` edge to `Head::first|second|…`. The coverage report follows the
 * chain through each method's declared return class. A lone call on the
 * class itself is a `calls` edge already, so it is left out. It runs after
 * the declaration visitor, which keeps the sink's place in the walk.
 */
final class CallChainVisitor extends NodeVisitorAbstract
{
    /**
     * @param SymbolSink    $symbolSink    Where the walk's findings go.
     * @param NameReader    $nameReader    Reads names.
     * @param ReceiverTypes $receiverTypes What the walk knows of receivers.
     */
    public function __construct(
        private readonly SymbolSink $symbolSink,
        private readonly NameReader $nameReader,
        private readonly ReceiverTypes $receiverTypes,
    ) {
    }

    /**
     * @param Node $node A node.
     */
    #[Override]
    public function enterNode(Node $node): null
    {
        $class = $this->symbolSink->currentClass();

        if ($node instanceof ClassLike) {
            $this->receiverTypes->enterClass($node, $class);
        }

        if ($node instanceof FunctionLike) {
            $this->receiverTypes->enterFunction($node, $class);
        }

        if ($node instanceof Assign) {
            $this->receiverTypes->assign($node, $class);
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall) {
            $this->call($node, $class);
        }

        return null;
    }

    /**
     * @param Node $node A node the walk leaves.
     */
    #[Override]
    public function leaveNode(Node $node): null
    {
        if ($node instanceof ClassLike) {
            $this->receiverTypes->leaveClass();
        }

        return null;
    }

    /**
     * @param MethodCall|NullsafeMethodCall|StaticCall $call  A call.
     * @param string                                   $class The class-like it sits in.
     */
    private function call(MethodCall|NullsafeMethodCall|StaticCall $call, string $class): void
    {
        $parent = [$call->getAttribute('parent')];
        $inner = ($parent[0] instanceof MethodCall || $parent[0] instanceof NullsafeMethodCall) && $parent[0]->var === $call;
        [$head, $names] = $inner
            ? ['', []]
            : $this->chain($call, $class);

        if (!self::counts($head, $names, $class)) {
            return;
        }

        $this->symbolSink->edge($head . '::' . implode('|', $names), 'chains');
    }

    /**
     * @param Expr   $expr  The outermost call of a chain.
     * @param string $class The class-like it sits in.
     *
     * @return array{string, list<string>} The first receiver's class and the methods called in order; an empty class when unknown.
     */
    private function chain(Expr $expr, string $class): array
    {
        $names = [];
        $node = $expr;

        while ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            if (!$node->name instanceof Identifier) {
                return ['', []];
            }

            array_unshift($names, $node->name->toString());
            $node = $node->var;
        }

        return $node instanceof StaticCall
            ? $this->staticHead($node, $class, $names)
            : [$this->receiverTypes->type($node, $class), $names];
    }

    /**
     * @param StaticCall   $staticCall The chain's first call.
     * @param string       $class      The class-like it sits in.
     * @param list<string> $names      The methods called on its result.
     *
     * @return array{string, list<string>} The class it names and every method in order; an empty class when unknown.
     */
    private function staticHead(StaticCall $staticCall, string $class, array $names): array
    {
        $named = $staticCall->class;
        $method = $staticCall->name;

        if (!$named instanceof Name || !$method instanceof Identifier) {
            return ['', []];
        }

        return [$this->nameReader->className($named, $class), [$method->toString(), ...$names]];
    }

    /**
     * @param string       $head  The first receiver's class; empty when unknown.
     * @param list<string> $names The methods called in order.
     * @param string       $class The class-like the chain sits in.
     *
     * @return bool Whether it is a chain to record: a known head, and more than one lone call on the class itself.
     */
    private static function counts(string $head, array $names, string $class): bool
    {
        return $head !== '' && $names !== [] && ($head !== $class || count($names) !== 1);
    }
}
