<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What one PHP file gave the index (#106): its symbols and edges, and
 * whether it parsed at all.
 */
final readonly class ParsedFile
{
    /**
     * @param list<CodeSymbol> $symbols Its symbols.
     * @param list<CodeEdge>   $edges   Its edges.
     * @param bool             $parsed  Whether php-parser read it; a file that does not parse gives nothing.
     */
    public function __construct(
        public array $symbols,
        public array $edges,
        public bool $parsed = true,
    ) {
    }
}
