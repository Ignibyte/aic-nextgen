<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_chunk;
use function array_map;
use function array_slice;
use function count;

/**
 * Splits a generated aggregator into chained parts (#92, review round 1): the
 * root class holds its first members and hands the rest to `<Root>Part2`, which
 * holds the next and hands on, so any number of modules fits while every
 * generated class keeps within PHPMD's coupling of 15 and PHPCS's 20-line
 * methods. Each aggregator picks its sizes from its own fixed types and lines.
 */
final readonly class ChainSource
{
    /**
     * @param string       $root     The root class's short name (`ModuleRoutes`).
     * @param list<string> $members  What the aggregator chains, in build order: generated per-module classes.
     * @param positive-int $rootSize The most members the root holds.
     * @param positive-int $partSize The most members each further part holds.
     *
     * @return non-empty-list<array{class: string, members: list<string>, next: string}> Each part: its class, its
     *                                                                                   members and the part after
     *                                                                                   it (empty for the last).
     */
    public function parts(string $root, array $members, int $rootSize, int $partSize): array
    {
        $chunks = [array_slice($members, 0, $rootSize), ...array_chunk(array_slice($members, $rootSize), $partSize)];
        $parts = [];

        foreach ($chunks as $index => $chunk) {
            $parts[] = [
                'class' => $this->name($root, $index),
                'members' => $chunk,
                'next' => $index + 1 < count($chunks) ? $this->name($root, $index + 1) : '',
            ];
        }

        return $parts;
    }

    /**
     * @param array{class: string, members: list<string>, next: string} $part A part.
     *
     * @return list<string> What it calls: its members, then the next part if there is one.
     */
    public function calls(array $part): array
    {
        return $part['next'] === ''
            ? $part['members']
            : [...$part['members'], $part['next']];
    }

    /**
     * @param list<string> $classes Generated classes' short names.
     *
     * @return list<string> Their full names in `App\Generated`, for the coupling count.
     */
    public function qualified(array $classes): array
    {
        return array_map(static fn (string $class): string => 'App\\Generated\\' . $class, $classes);
    }

    /**
     * @param string $root  The root class's short name.
     * @param int    $index A part's position, the root's 0.
     *
     * @return string The part's class: the root, then `<Root>Part2`, `<Root>Part3`, …
     */
    private function name(string $root, int $index): string
    {
        return $index === 0
            ? $root
            : $root . 'Part' . ($index + 1);
    }
}
