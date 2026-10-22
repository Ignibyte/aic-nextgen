<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeAnswer;
use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function explode;

/**
 * A symbol's references as the code index tells them (#106): what names it
 * and what it names, grouped by edge kind, 25 lines per kind at most.
 */
final readonly class CodeRefsView
{
    /**
     * The lines one kind shows at most.
     */
    private const int EDGES = 25;

    /**
     * @param CodeLookup $codeLookup Reads the index.
     * @param CodeLines  $codeLines  Says things.
     */
    public function __construct(
        private CodeLookup $codeLookup,
        private CodeLines $codeLines,
    ) {
    }

    /**
     * @param CodeSymbol $codeSymbol The symbol asked about.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return CodeAnswer What refers to it and what it refers to.
     */
    public function refs(CodeSymbol $codeSymbol): CodeAnswer
    {
        $incoming = $this->codeLookup->incoming($codeSymbol->name);
        $outgoing = $this->codeLookup->outgoing($codeSymbol->name);
        return new CodeAnswer(
            [
                $this->codeLines->symbol($codeSymbol),
                'referred to by (' . count($incoming) . '):',
                ...$this->grouped($incoming, true),
                'refers to (' . count($outgoing) . '):',
                ...$this->grouped($outgoing, false),
            ],
            $codeSymbol->file === '' ? [] : [$codeSymbol->file],
            [],
        );
    }

    /**
     * @param list<CodeEdge> $edges    Edges.
     * @param bool           $incoming Whether they name the symbol.
     *
     * @return list<string> One line each, grouped by kind, each kind capped.
     */
    private function grouped(array $edges, bool $incoming): array
    {
        $byKind = [];

        foreach ($edges as $edge) {
            $byKind[$edge->kind][] = $incoming
                ? $this->from($edge)
                : $this->into($edge);
        }

        $lines = [];

        foreach ($byKind as $kind => $kindLines) {
            $lines = [...$lines, ...$this->capped($kind, array_values(array_unique($kindLines)))];
        }

        return $lines;
    }

    /**
     * @param string       $kind  An edge kind.
     * @param list<string> $lines Its lines.
     *
     * @return list<string> The first 25, and a line saying how many more.
     */
    private function capped(string $kind, array $lines): array
    {
        return count($lines) > self::EDGES
            ? [...array_slice($lines, 0, self::EDGES), '  … ' . (count($lines) - self::EDGES) . ' more ' . $kind]
            : $lines;
    }

    /**
     * @param CodeEdge $codeEdge An edge that names the symbol.
     *
     * @return string Its kind, where it starts and its file.
     */
    private function from(CodeEdge $codeEdge): string
    {
        return '  ' . $codeEdge->kind . ' ' . $codeEdge->source . ($codeEdge->file === '' ? '' : ' — ' . $codeEdge->file);
    }

    /**
     * @param CodeEdge $codeEdge An edge that starts at the symbol or one of its methods.
     *
     * @return string Its kind, what it names, and the method it sits in.
     */
    private function into(CodeEdge $codeEdge): string
    {
        $method = explode('::', $codeEdge->source)[1] ?? '';

        return '  ' . $codeEdge->kind . ' ' . $codeEdge->target . ($method === '' ? '' : ' (in ' . $method . ')');
    }
}
