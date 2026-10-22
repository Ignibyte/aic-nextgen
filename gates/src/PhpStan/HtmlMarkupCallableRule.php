<?php

declare(strict_types=1);

namespace Gates\PhpStan;

use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodCallableNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Only the response writer reads HTML, as a first-class callable too
 * (gate:8, #99): `$html->markup(...)` is PHPStan's own node, so it has its
 * own rule beside HtmlMarkupReadRule.
 *
 * @implements Rule<MethodCallableNode>
 */
final readonly class HtmlMarkupCallableRule implements Rule
{
    /**
     * @param HtmlReads $htmlReads The check.
     */
    public function __construct(
        private HtmlReads $htmlReads = new HtmlReads(),
    ) {
    }

    /**
     * @return class-string<MethodCallableNode> The node this rule inspects.
     */
    #[Override]
    public function getNodeType(): string
    {
        return MethodCallableNode::class;
    }

    /**
     * @param MethodCallableNode $node  A first-class callable method.
     * @param Scope              $scope Where it is.
     *
     * @return list<IdentifierRuleError> One error when it reads an Html's markup outside the writer.
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        return $this->htmlReads->errors($node->getVar(), $node->getName(), $scope);
    }
}
