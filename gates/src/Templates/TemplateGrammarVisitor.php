<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateScope;
use Gates\Domain\TemplateViolation;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Token;

use function is_string;

/**
 * Walks one template's statements against the grammar (#96). Each node's type
 * is looked up in `TemplateNodeTable`: allowed, checked by a rule, or refused.
 * A refused node gives one violation and its children are not walked, so one
 * construct gives one line. A `for` or `foreach` brings its variables into
 * scope for its body, and they leave with it.
 */
final class TemplateGrammarVisitor extends NodeVisitorAbstract
{
    /**
     * The attribute marking the typing line, which the head check reads instead.
     */
    public const string HEAD = 'templateHead';

    /**
     * The attribute marking a loop whose variables the walk pushed, so leaving it pops them.
     */
    private const string SCOPED = 'templateScoped';

    /**
     * @var list<TemplateViolation> What the walk refused, in order.
     */
    private array $violations = [];

    /**
     * @param string                  $path                    The template, for the violations.
     * @param list<Token>             $tokens                  Its tokens.
     * @param TemplateScope           $templateScope           The variables in scope.
     * @param TemplateNodeTable       $templateNodeTable       The grammar's table.
     * @param TemplateStatementRules  $templateStatementRules  The statement checks.
     * @param TemplateExpressionRules $templateExpressionRules The expression checks.
     */
    public function __construct(
        private readonly string $path,
        private readonly array $tokens,
        private readonly TemplateScope $templateScope,
        private readonly TemplateNodeTable $templateNodeTable = new TemplateNodeTable(),
        private readonly TemplateStatementRules $templateStatementRules = new TemplateStatementRules(),
        private readonly TemplateExpressionRules $templateExpressionRules = new TemplateExpressionRules(),
    ) {
    }

    /**
     * @param Node $node A node.
     *
     * @return int|null `DONT_TRAVERSE_CHILDREN` for a refused node or the typing line; null to walk on.
     */
    #[Override]
    public function enterNode(Node $node): ?int
    {
        if ($node->getAttribute(self::HEAD) === true) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }

        $refusal = $this->refusal($node);

        if ($refusal !== null) {
            $this->violations[] = new TemplateViolation($this->path, $node->getStartLine(), $refusal[0], $refusal[1]);

            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }

        $this->enterLoop($node);

        return null;
    }

    /**
     * @param Node $node A node.
     */
    #[Override]
    public function leaveNode(Node $node): null
    {
        if ($node->getAttribute(self::SCOPED) === true) {
            $this->templateScope->pop();
        }

        return null;
    }

    /**
     * @return list<TemplateViolation> What the walk refused.
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @param Node $node A node.
     *
     * @return array{TemplateRefusal, string}|null Its refusal; null when the grammar allows it.
     */
    private function refusal(Node $node): ?array
    {
        $type = $node->getType();

        if ($this->templateNodeTable->allowed($type)) {
            return null;
        }

        if (!$this->templateNodeTable->checked($type)) {
            return $this->templateNodeTable->refusal($type);
        }

        return $node instanceof Stmt
            ? $this->templateStatementRules->check($node, $this->tokens, $this->templateScope)
            : $this->templateExpressionRules->check($node, $this->templateScope);
    }

    /**
     * Bring a loop's variables into scope, marking it so leaving it pops them; a `for` header's expressions may assign.
     *
     * @param Node $node A node the grammar allows.
     */
    private function enterLoop(Node $node): void
    {
        if ($node instanceof Foreach_) {
            $node->setAttribute(self::SCOPED, true);
            $this->templateScope->push($this->names([$node->keyVar, $node->valueVar]));

            return;
        }

        if (!$node instanceof For_) {
            return;
        }

        $assigned = [];

        foreach ([...$node->init, ...$node->loop] as $expr) {
            $expr->setAttribute(TemplateExpressionRules::FOR_HEADER, true);
            $assigned[] = $expr instanceof Assign
                ? $expr->var
                : null;
        }

        $node->setAttribute(self::SCOPED, true);
        $this->templateScope->push($this->names($assigned));
    }

    /**
     * @param list<Node|null> $nodes Loop variables, or null where there is none.
     *
     * @return list<string> The plain variables' names.
     */
    private function names(array $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            if (!($node instanceof Variable) || !is_string($node->name)) {
                continue;
            }

            $names[] = $node->name;
        }

        return $names;
    }
}
