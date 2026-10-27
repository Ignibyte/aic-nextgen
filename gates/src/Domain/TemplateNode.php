<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One node of a lowered component template (#36, #97): a run of text, an
 * output, a block with the nodes inside it, a loop's control, or an import.
 */
final readonly class TemplateNode
{
    /**
     * @param TemplateNodeKind   $kind      What it is.
     * @param string             $code      The text, the PHP expression, a loop's header, or the imported name.
     * @param list<TemplateNode> $children  A block's nodes; for a condition, the nodes shown when it holds.
     * @param list<TemplateNode> $otherwise A condition's nodes shown when it does not hold.
     * @param string             $binding   A component's key expression, empty for none.
     * @param string             $key       A component's compile-time key, `Name.N`.
     */
    public function __construct(
        public TemplateNodeKind $kind,
        public string $code,
        public array $children = [],
        public array $otherwise = [],
        public string $binding = '',
        public string $key = '',
    ) {
    }
}
