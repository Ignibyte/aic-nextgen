<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Exception\WorkflowRefusal;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

use function array_filter;
use function array_values;

/** Parse source and resolve class aliases without executing a manifest or changing source offsets. */
final readonly class ManifestSyntax
{
    /**
     * @param string $text The manifest source.
     * @param string $file Its path for a refusal.
     *
     * @throws WorkflowRefusal When source or its imports cannot be resolved.
     *
     * @return list<Stmt> Resolved top-level statements with their original file positions.
     */
    public function parse(string $text, string $file): array
    {
        try {
            $stmts = array_values((new ParserFactory())->createForHostVersion()->parse($text) ?? []);

            return array_values(array_filter(
                (new NodeTraverser(new NameResolver()))->traverse($stmts),
                static fn (Node $node): bool => $node instanceof Stmt,
            ));
        } catch (Error $error) {
            throw new WorkflowRefusal($file . ' does not parse: ' . $error->getMessage(), 0, $error);
        }
    }
}
