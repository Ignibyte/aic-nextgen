<?php

declare(strict_types=1);

namespace Gates\Templates;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\AssignOp\Minus;
use PhpParser\Node\Expr\AssignOp\Plus;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Scalar\Int_;
use PhpParser\NodeVisitorAbstract;

/**
 * Rewrites a `for` header's increments for printing (#97): PHPCS forbids
 * `++` and `--`, so each prints as `+= 1` or `-= 1`. gate:25 allows them only
 * as a whole init or step expression, whose value is dropped, so the two
 * forms agree.
 */
final class TemplateStepVisitor extends NodeVisitorAbstract
{
    /**
     * @param Node $node A node of the expression.
     *
     * @return Node|null The increment or decrement as `+= 1` or `-= 1`; null to leave the node.
     */
    #[Override]
    public function leaveNode(Node $node): ?Node
    {
        return match (true) {
            $node instanceof PreInc, $node instanceof PostInc => new Plus($node->var, new Int_(1)),
            $node instanceof PreDec, $node instanceof PostDec => new Minus($node->var, new Int_(1)),
            default => null,
        };
    }
}
