<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Only the response writer reads HTML (gate:8, #99): `->markup()` on an Html,
 * plain or nullsafe, anywhere but `AIC\Controller\Application\Psr7ResponseWriter`
 * is refused. The receiver's type decides, so another class's `markup()` is
 * not this rule's; HtmlMarkupCallableRule takes the first-class callable.
 *
 * @implements Rule<CallLike>
 */
final readonly class HtmlMarkupReadRule implements Rule
{
    /**
     * @param HtmlReads $htmlReads The check.
     */
    public function __construct(
        private HtmlReads $htmlReads = new HtmlReads(),
    ) {
    }

    /**
     * @return class-string<CallLike> The node this rule inspects.
     */
    #[Override]
    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param CallLike $node  A call.
     * @param Scope    $scope Where it is.
     *
     * @return list<IdentifierRuleError> One error when it reads an Html's markup outside the writer.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        return $node instanceof MethodCall || $node instanceof NullsafeMethodCall
            ? $this->htmlReads->errors($node->var, $node->name, $scope)
            : [];
    }
}
