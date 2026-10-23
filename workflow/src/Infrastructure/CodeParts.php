<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\CodeIndex;
use AIC\Workflow\Application\CodeLines;
use AIC\Workflow\Application\CodeModuleView;
use AIC\Workflow\Application\CodeOutline;
use AIC\Workflow\Application\CodeRefsView;
use AIC\Workflow\Application\CodeTree;

/**
 * The code index's parts for one project (#106): the indexer, the lookup
 * and the answers. #118's plan check takes the index and the lookup from
 * here too.
 */
final readonly class CodeParts
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return CodeIndex The index, kept current.
     */
    public function codeIndex(): CodeIndex
    {
        $localHost = new LocalHost($this->root);

        return new CodeIndexer($this->root, $localHost, new CodeSurfaceGraph($this->root, new SurfaceEntries($this->root, $localHost)));
    }

    /**
     * @return CodeIndexQueries The index, read.
     */
    public function codeLookup(): CodeIndexQueries
    {
        return new CodeIndexQueries($this->root);
    }

    /**
     * @return CodeTree The answers.
     */
    public function codeTree(): CodeTree
    {
        $codeLookup = $this->codeLookup();
        $codeLines = new CodeLines();
        $codeModuleView = new CodeModuleView($codeLookup, $codeLines);

        return new CodeTree(
            $codeLookup,
            new CodeOutline($codeLookup, $codeModuleView),
            $codeModuleView,
            new CodeRefsView($codeLookup, $codeLines),
            $codeLines,
        );
    }
}
