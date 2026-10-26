<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use InvalidArgumentException;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeVisitorAbstract;

use function array_unique;
use function array_values;

/**
 * Rewrites one template expression for printing (#97): `empty()` lowered by
 * type, and each class name required fully qualified and printed so, recorded
 * for the loop-variable naming rule (#155).
 */
final class TemplateNameVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<string> The classes the expression names, fully qualified.
     */
    private array $imports = [];

    /**
     * @param TemplateEmpty               $templateEmpty Lowers `empty()`.
     * @param array<string, TemplateType> $scope         The variables in scope.
     */
    public function __construct(
        private readonly TemplateEmpty $templateEmpty,
        private readonly array $scope,
    ) {
    }

    /**
     * @param Node $node A node of the expression.
     *
     * @throws InvalidArgumentException When `empty()`'s operand has no plain type.
     *
     * @return Node|null `empty()` lowered; null to leave the node.
     */
    #[Override]
    public function enterNode(Node $node): ?Node
    {
        return $node instanceof Empty_
            ? $this->templateEmpty->lower($node->expr, $this->scope)
            : null;
    }

    /**
     * @param Node $node A node of the expression.
     *
     * @throws InvalidArgumentException When a class name is not fully qualified.
     */
    #[Override]
    public function leaveNode(Node $node): null
    {
        if (!$node instanceof ClassConstFetch || !$node->class instanceof Name) {
            return null;
        }

        if (!$node->class instanceof FullyQualified) {
            throw new InvalidArgumentException(TemplateRefusal::ClassName->value . ': write the class fully qualified: \\'
                . $node->class->toString() . '::' . ($node->name instanceof Identifier ? $node->name->toString() : ''));
        }

        $this->imports[] = $node->class->toString();

        return null;
    }

    /**
     * @return list<string> The classes the expression names, each once.
     */
    public function imports(): array
    {
        return array_values(array_unique($this->imports));
    }
}
