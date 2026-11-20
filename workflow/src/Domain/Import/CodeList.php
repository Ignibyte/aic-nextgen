<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * A PHP list of nodes (#318), such as the terms of `concat([…])`.
 */
final readonly class CodeList implements CodeNode
{
    /**
     * @param list<CodeNode> $items The items.
     */
    public function __construct(
        public array $items,
    ) {
    }
}
