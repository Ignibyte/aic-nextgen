<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\CodeCoverageCommand;
use AIC\Workflow\Application\CodeIndex;
use AIC\Workflow\Application\Coverage\CodeCoverage;

/**
 * The coverage report's composition (#232): `code:coverage` over the
 * project's index and files.
 */
final readonly class CoverageKernel
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param CodeIndex $codeIndex The index, refreshed before the report.
     *
     * @return Command `code:coverage`.
     */
    public function command(CodeIndex $codeIndex): Command
    {
        $coverageIndexQueries = new CoverageIndexQueries(new CodeIndexRows($this->root));

        return new CodeCoverageCommand($codeIndex, new CodeCoverage($coverageIndexQueries, new CoverageFiles($this->root)));
    }
}
