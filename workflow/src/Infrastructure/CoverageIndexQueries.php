<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Coverage\CoverageLookup;
use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use Override;

/**
 * The code index as the coverage report reads it, over SQLite (#232): every
 * PHP declaration and edge, and every file's area.
 */
final readonly class CoverageIndexQueries implements CoverageLookup
{
    /**
     * @param CodeIndexRows $codeIndexRows Reads its rows.
     */
    public function __construct(
        private CodeIndexRows $codeIndexRows,
    ) {
    }

    /**
     * @return list<CodeSymbol> Every class-like and method.
     */
    #[Override]
    public function declarations(): array
    {
        return $this->codeIndexRows->symbols(
            CodeIndexRows::SYMBOL . " WHERE origin = 'php' AND kind IN ('class', 'interface', 'enum', 'trait', 'method')",
            [],
        );
    }

    /**
     * @return list<CodeEdge> Every edge PHP gave.
     */
    #[Override]
    public function edges(): array
    {
        return $this->codeIndexRows->edges(CodeIndexRows::EDGE . " WHERE origin = 'php'", []);
    }

    /**
     * @return array<string, string> Each file's area, by path.
     */
    #[Override]
    public function fileAreas(): array
    {
        $areas = [];

        foreach ($this->codeIndexRows->rows('SELECT path, area FROM file', []) as $jsonReader) {
            $areas[$jsonReader->string('path')] = $jsonReader->string('area');
        }

        return $areas;
    }
}
