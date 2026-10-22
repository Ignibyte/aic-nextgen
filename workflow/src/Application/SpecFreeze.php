<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use function array_diff;
use function array_values;
use function in_array;

/**
 * Compares a spec with the one frozen when planning ended (#101, Droost's
 * `SpecFreeze`): every frozen section is append-only, so a frozen row that is
 * gone or rewritten is a breach, and a manifest row added since is declared
 * late.
 */
final readonly class SpecFreeze
{
    /**
     * @param SpecContract $frozen The spec planning ended on.
     * @param SpecContract $now    The spec now.
     *
     * @return list<string> Each frozen row the spec lost, with its section: `### File Manifest: "| a | b |"`.
     */
    public function breaches(SpecContract $frozen, SpecContract $now): array
    {
        $current = $now->frozenRows();
        $breaches = [];

        foreach ($frozen->frozenRows() as $section => $rows) {
            $breaches = [...$breaches, ...$this->lost($section, $rows, $current[$section] ?? [])];
        }

        return $breaches;
    }

    /**
     * @param SpecContract $frozen The spec planning ended on.
     * @param SpecContract $now    The spec now.
     *
     * @return list<string> The manifest's paths declared since planning ended.
     */
    public function late(SpecContract $frozen, SpecContract $now): array
    {
        return array_values(array_diff($now->files(), $frozen->files()));
    }

    /**
     * @param string       $section A section's heading.
     * @param list<string> $frozen  Its frozen rows.
     * @param list<string> $now     Its rows now.
     *
     * @return list<string> The frozen rows it no longer holds, quoted after the heading.
     */
    private function lost(string $section, array $frozen, array $now): array
    {
        $lost = [];

        foreach ($frozen as $row) {
            if (in_array($row, $now, true)) {
                continue;
            }

            $lost[] = $section . ': "' . $row . '"';
        }

        return $lost;
    }
}
