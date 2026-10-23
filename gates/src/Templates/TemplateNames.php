<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateType;
use InvalidArgumentException;
use PhpParser\Node\Expr;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\PrettyPrinter\Standard;

/**
 * Prints a template expression into the generated class (#97), through
 * php-parser's pretty printer. A class name in a template has no `use` to
 * lean on, so it is written fully qualified and printed short, with an
 * import; TemplateEmpty lowers `empty()` and TemplateStepVisitor a `for`
 * header's increments.
 */
final readonly class TemplateNames
{
    /**
     * @param TemplateEmpty $templateEmpty Lowers `empty()`.
     * @param Standard      $standard      Prints the rewritten expression.
     */
    public function __construct(
        private TemplateEmpty $templateEmpty = new TemplateEmpty(),
        private Standard $standard = new Standard(),
    ) {
    }

    /**
     * @param Expr                        $expr  An expression the grammar allows.
     * @param array<string, TemplateType> $scope The variables in scope, by name.
     *
     * @throws InvalidArgumentException When a class name is not fully qualified, or `empty()`'s operand has no plain type.
     *
     * @return array{string, list<string>} The code, and the classes it imports.
     */
    public function print(Expr $expr, array $scope): array
    {
        $clone = (new NodeTraverser(new CloningVisitor()))->traverse([$expr])[0] ?? null;

        if (!$clone instanceof Expr) {
            throw new InvalidArgumentException(TemplateRefusal::NotCompilable->value . ': the expression did not survive its clone');
        }

        $templateNameVisitor = new TemplateNameVisitor($this->templateEmpty, $scope);
        $rewritten = (new NodeTraverser($templateNameVisitor, new TemplateStepVisitor()))->traverse([$clone])[0] ?? null;

        if (!$rewritten instanceof Expr) {
            throw new InvalidArgumentException(TemplateRefusal::NotCompilable->value . ': the expression did not survive its rewrite');
        }

        return [$this->standard->prettyPrintExpr($rewritten), $templateNameVisitor->imports()];
    }
}
