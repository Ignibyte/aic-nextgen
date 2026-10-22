<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;

use function implode;
use function str_replace;

/**
 * How the code index's answers say things (#106): a symbol on one line,
 * a list joined or `none`, an edge list's ends.
 */
final readonly class CodeLines
{
    /**
     * @param CodeSymbol $codeSymbol A symbol.
     *
     * @return string Its kind, name, detail and place.
     */
    public function symbol(CodeSymbol $codeSymbol): string
    {
        $where = $codeSymbol->line > 0
            ? $codeSymbol->file . ':' . $codeSymbol->line
            : $codeSymbol->file;
        $detail = $codeSymbol->detail === ''
            ? ''
            : ' [' . $codeSymbol->detail . ']';

        return $codeSymbol->kind . ' ' . $codeSymbol->name . $detail . ($where === '' ? '' : ' — ' . $where);
    }

    /**
     * @param list<string> $items Words.
     *
     * @return string Them joined; `none` when there are none.
     */
    public function joined(array $items): string
    {
        return $items === []
            ? 'none'
            : implode(', ', $items);
    }

    /**
     * @param list<CodeEdge> $edges    Edges.
     * @param string         $kind     A kind.
     * @param bool           $incoming Whether to read their sources (true) or their targets.
     *
     * @return list<string> The ends of those of that kind, without a `module:` prefix.
     */
    public function ends(array $edges, string $kind, bool $incoming): array
    {
        $ends = [];

        foreach ($edges as $edge) {
            if ($edge->kind !== $kind) {
                continue;
            }

            $ends[] = str_replace('module:', '', $incoming ? $edge->source : $edge->target);
        }

        return $ends;
    }
}
