<?php

declare(strict_types=1);

namespace Gates\Rendering;

use AIC\Component\Domain\RawHtml;
use Gates\Domain\RawHtmlUse;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeVisitorAbstract;

/**
 * Finds RawHtml in one file, its names resolved (gate:27, #99): each
 * `new RawHtml`, a use, and each `RawHtml::` reference, which could build one
 * through a class-string.
 */
final class RawHtmlUseVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<RawHtmlUse> Each `new RawHtml`.
     */
    private array $uses = [];

    /**
     * @var list<RawHtmlUse> Each `RawHtml::` reference.
     */
    private array $references = [];

    /**
     * The class being walked, fully qualified; empty outside one.
     */
    private string $class = '';

    /**
     * @param string $path The file, relative to the root.
     */
    public function __construct(
        private readonly string $path,
    ) {
    }

    /**
     * @param Node $node A node of the file.
     */
    #[Override]
    public function enterNode(Node $node): null
    {
        if ($node instanceof ClassLike && $node->namespacedName instanceof Name) {
            $this->class = $node->namespacedName->toString();
        }

        $this->record($node);

        return null;
    }

    /**
     * @return list<RawHtmlUse> Each `new RawHtml` in the file.
     */
    public function uses(): array
    {
        return $this->uses;
    }

    /**
     * @return list<RawHtmlUse> Each `RawHtml::` reference in the file.
     */
    public function references(): array
    {
        return $this->references;
    }

    /**
     * @param Node $node A node of the file: a `new RawHtml` is a use, a `RawHtml::` a reference.
     */
    private function record(Node $node): void
    {
        if ($node instanceof New_ && self::namesRawHtml($node->class)) {
            $this->uses[] = new RawHtmlUse($this->path, $node->getStartLine(), $this->class);
        }

        if (!($node instanceof ClassConstFetch) || !self::namesRawHtml($node->class)) {
            return;
        }

        $this->references[] = new RawHtmlUse($this->path, $node->getStartLine(), $this->class);
    }

    /**
     * @param Node $node A class part of an expression.
     *
     * @return bool Whether it is the name RawHtml, as resolved.
     */
    private static function namesRawHtml(Node $node): bool
    {
        return $node instanceof Name && $node->toString() === RawHtml::class;
    }
}
