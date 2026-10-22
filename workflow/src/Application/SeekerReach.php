<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_diff;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function sort;

/**
 * The seeker's one hop (#127): for each changed file, the files one edge
 * away in the code index, both ways. Out, each class-like its symbols name,
 * resolved to the file that declares it; in, the file of every edge that
 * names one of its symbols. A name the index does not hold (a PHP builtin)
 * adds nothing.
 */
final readonly class SeekerReach
{
    /**
     * @param CodeIndex  $codeIndex  Refreshed first, so the edges are the tree's.
     * @param CodeLookup $codeLookup The edges and where each symbol is.
     */
    public function __construct(
        private CodeIndex $codeIndex,
        private CodeLookup $codeLookup,
    ) {
    }

    /**
     * @param list<string> $changed The run's changed paths.
     *
     * @throws WorkflowRefusal When the index cannot be refreshed or read.
     *
     * @return list<string> The files one hop from them, sorted, the changed ones left out.
     */
    public function files(array $changed): array
    {
        $this->codeIndex->refresh(false);
        $near = array_merge([], ...array_map($this->near(...), $changed));
        $files = array_values(array_diff(array_unique($near), $changed));
        sort($files);

        return $files;
    }

    /**
     * @param string $path A changed path.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<string> The files one hop from its symbols, either way.
     */
    private function near(string $path): array
    {
        $symbols = array_filter(
            $this->codeLookup->symbolsUnder($path),
            static fn (CodeSymbol $codeSymbol): bool => $codeSymbol->file === $path && $codeSymbol->kind !== 'method',
        );

        return array_merge([], ...array_map($this->edges(...), array_values($symbols)));
    }

    /**
     * @param CodeSymbol $codeSymbol A symbol in a changed file.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<string> The files that declare what it names, and the files that name it.
     */
    private function edges(CodeSymbol $codeSymbol): array
    {
        $used = array_map($this->declared(...), $this->codeLookup->outgoing($codeSymbol->name));
        $users = array_map(static fn (CodeEdge $codeEdge): string => $codeEdge->file, $this->codeLookup->incoming($codeSymbol->name));

        return [...array_merge([], ...$used), ...$users];
    }

    /**
     * @param CodeEdge $codeEdge An edge out of a changed file.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<string> The file that declares its target's class, when the index holds it.
     */
    private function declared(CodeEdge $codeEdge): array
    {
        $class = explode('::', $codeEdge->target)[0];
        $named = array_filter($this->codeLookup->named($class), static fn (CodeSymbol $codeSymbol): bool => $codeSymbol->name === $class);

        return array_values(array_map(static fn (CodeSymbol $codeSymbol): string => $codeSymbol->file, $named));
    }
}
