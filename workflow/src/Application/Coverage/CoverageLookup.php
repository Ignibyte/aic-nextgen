<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The code index as the coverage report reads it (#232): every declaration,
 * every edge and every file's area, from PHP.
 */
interface CoverageLookup
{
    /**
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeSymbol> Every class-like and method, with each method's return class and visibility.
     */
    public function declarations(): array;

    /**
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeEdge> Every edge PHP gave, call chains included.
     */
    public function edges(): array;

    /**
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return array<string, string> Each indexed file's area, by path.
     */
    public function fileAreas(): array;
}
