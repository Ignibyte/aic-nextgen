<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeCrossing;
use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Reads the code index (#106).
 */
interface CodeLookup
{
    /**
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return array<string, array{files: int, symbols: int}> Each area's counts, by area.
     */
    public function areas(): array;

    /**
     * @param string $area An area, such as `module:note`.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeSymbol> Its symbols, by file and line.
     */
    public function inArea(string $area): array;

    /**
     * @param string $prefix A path, or the start of one.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<string> The indexed files under it, sorted.
     */
    public function filesUnder(string $prefix): array;

    /**
     * @param string $prefix A path, or the start of one.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeSymbol> The symbols in the files under it, by file and line.
     */
    public function symbolsUnder(string $prefix): array;

    /**
     * @param string $text  What a name contains, case-insensitively.
     * @param int    $limit How many at most.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeSymbol> The symbols whose name contains it, PHP symbols before surface ones.
     */
    public function search(string $text, int $limit): array;

    /**
     * @param string $name A full name, or a last segment.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeSymbol> The symbols with that full name; when none, those whose last segment it is.
     */
    public function named(string $name): array;

    /**
     * @param string $name A symbol's full name.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeEdge> The edges that name it, or one of its methods.
     */
    public function incoming(string $name): array;

    /**
     * @param string $name A symbol's full name.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeEdge> The edges that start at it, or at one of its methods.
     */
    public function outgoing(string $name): array;

    /**
     * @param string $area An area, such as `module:note`.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return list<CodeCrossing> Each class-like named across the area's edge, with the naming area and its own (#108).
     */
    public function crossing(string $area): array;
}
