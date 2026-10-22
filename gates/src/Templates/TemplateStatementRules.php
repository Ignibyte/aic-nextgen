<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateScope;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Token;

use function count;
use function in_array;
use function is_string;

use const T_CLOSE_TAG;
use const T_ENDFOR;
use const T_ENDFOREACH;
use const T_ENDIF;
use const T_ENDSWITCH;
use const T_OPEN_TAG_WITH_ECHO;
use const T_WHITESPACE;

/**
 * The checks on a template's statements (#96): `<?=` with one expression, the
 * alternative syntax for control flow, a `foreach` that binds plain new
 * variables, and no `<?php ?>` block holding anything but control flow.
 */
final readonly class TemplateStatementRules
{
    /**
     * The detail for a comment block: the one comment is the typing line.
     */
    private const string COMMENT = 'a <?php ?> block holds only control flow; the one comment is the typing line';

    /**
     * php-parser's token id for `;`, a one-character token: its byte value.
     */
    private const int SEMICOLON = 59;

    /**
     * The keyword an alternative-syntax statement ends on.
     */
    private const array ENDINGS = [
        'Stmt_For' => T_ENDFOR,
        'Stmt_Foreach' => T_ENDFOREACH,
        'Stmt_If' => T_ENDIF,
        'Stmt_Switch' => T_ENDSWITCH,
    ];

    /**
     * @param TemplateNodeTable $templateNodeTable Names an inner expression's refusal.
     */
    public function __construct(
        private TemplateNodeTable $templateNodeTable = new TemplateNodeTable(),
    ) {
    }

    /**
     * @param Node          $node          A checked statement.
     * @param list<Token>   $tokens        The template's tokens.
     * @param TemplateScope $templateScope The variables in scope.
     *
     * @return array{TemplateRefusal, string}|null Its refusal and detail; null when it passes.
     */
    public function check(Node $node, array $tokens, TemplateScope $templateScope): ?array
    {
        return match (true) {
            $node instanceof Echo_ => $this->echo($node, $tokens),
            $node instanceof Expression => $this->expression($node),
            $node instanceof Foreach_ => $this->alternative($node, $tokens) ?? $this->foreach($node, $templateScope),
            $node->getType() === 'Stmt_Nop' => [TemplateRefusal::PhpBlock, self::COMMENT],
            default => $this->alternative($node, $tokens),
        };
    }

    /**
     * @param Echo_       $echo   An echo statement.
     * @param list<Token> $tokens The template's tokens.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless it is `<?= expression ?>`.
     */
    private function echo(Echo_ $echo, array $tokens): ?array
    {
        if ($tokens[$echo->getStartTokenPos()]->id !== T_OPEN_TAG_WITH_ECHO) {
            return [TemplateRefusal::EchoPrint, 'write <?= expression ?>'];
        }

        return count($echo->exprs) === 1
            ? null
            : [TemplateRefusal::NotInGrammar, 'one expression per <?= ?>'];
    }

    /**
     * @param Expression $expression A statement holding a bare expression: `<?php x; ?>`.
     *
     * @return array{TemplateRefusal, string} Always a refusal: the expression's own, else `php block`.
     */
    private function expression(Expression $expression): array
    {
        $type = $expression->expr->getType();

        if ($this->templateNodeTable->allowed($type) || $this->templateNodeTable->checked($type)) {
            return [TemplateRefusal::PhpBlock, $this->templateNodeTable->detail(TemplateRefusal::PhpBlock)];
        }

        return $this->templateNodeTable->refusal($type);
    }

    /**
     * @param Node        $node   An `if`, `for`, `foreach` or `switch`.
     * @param list<Token> $tokens The template's tokens.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless it ends on its alternative-syntax keyword.
     */
    private function alternative(Node $node, array $tokens): ?array
    {
        $end = $node->getEndTokenPos();

        while ($end > 0 && in_array($tokens[$end]->id, [T_WHITESPACE, T_CLOSE_TAG, self::SEMICOLON], true)) {
            $end -= 1;
        }

        return $tokens[$end]->id === (self::ENDINGS[$node->getType()] ?? 0)
            ? null
            : [TemplateRefusal::ControlSyntax, 'control flow uses the alternative syntax: if (...): ... endif'];
    }

    /**
     * @param Foreach_      $foreach       A `foreach` in the alternative syntax.
     * @param TemplateScope $templateScope The variables in scope.
     *
     * @return array{TemplateRefusal, string}|null The refusal unless it binds plain, new variables by value.
     */
    private function foreach(Foreach_ $foreach, TemplateScope $templateScope): ?array
    {
        $bound = $foreach->keyVar === null
            ? [$foreach->valueVar]
            : [$foreach->keyVar, $foreach->valueVar];

        foreach ($bound as $variable) {
            if (!$this->fresh($variable, $templateScope)) {
                return [TemplateRefusal::Assignment, 'foreach binds plain, new variables: foreach ($c->items as $item)'];
            }
        }

        return $foreach->byRef
            ? [TemplateRefusal::Assignment, 'foreach binds by value']
            : null;
    }

    /**
     * @param Node          $node          A variable a `foreach` binds.
     * @param TemplateScope $templateScope The variables in scope.
     *
     * @return bool Whether it is a plain variable that names nothing in scope yet.
     */
    private function fresh(Node $node, TemplateScope $templateScope): bool
    {
        return $node instanceof Variable && is_string($node->name) && !$templateScope->has($node->name);
    }
}
