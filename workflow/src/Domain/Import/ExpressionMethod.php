<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * A private method of an imported table's migration that returns one expression too long for the line that uses
 * it (#318).
 */
final readonly class ExpressionMethod
{
    /**
     * @param string   $name     The method.
     * @param string   $returns  `Term` or `Predicate`.
     * @param CodeNode $codeNode The expression.
     * @param string   $about    What it is, for its docblock: "The `pages_bounded` check".
     */
    public function __construct(
        public string $name,
        public string $returns,
        public CodeNode $codeNode,
        public string $about,
    ) {
    }
}
