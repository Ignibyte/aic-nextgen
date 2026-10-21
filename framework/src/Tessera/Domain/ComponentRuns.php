<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

use function array_pop;
use function explode;
use function implode;
use function strlen;
use function strpos;
use function substr;

/**
 * Finds a component in rendered markup by its keys (#70): the run from its
 * `<!--t:c KEY-->` to its `<!--/t:c-->`, as protocol 2.2 marks it. AIC wrote
 * the markers, so a scan of the markup is exact.
 */
final readonly class ComponentRuns
{
    private const string OPEN = '<!--t:c ';

    private const string CLOSE = '<!--/t:c-->';

    /**
     * @param string       $markup A region's rendered markup.
     * @param list<string> $keys   The component's keys from the outside in, below the region.
     *
     * @return string Its run, markers included; empty when the markup has no such component.
     */
    public function find(string $markup, array $keys): string
    {
        $path = implode('/', $keys);

        foreach ($this->runs($markup) as $run) {
            if ($run instanceof Run && $run->path === $path) {
                return substr($markup, $run->start, $run->end - $run->start);
            }
        }

        return '';
    }

    /**
     * @param string $markup The markup.
     *
     * @return list<object> Every component's Run, in the order they close.
     */
    private function runs(string $markup): array
    {
        $runs = [];
        $open = [];
        $starts = [];

        foreach ($this->markers($markup) as $marker) {
            if (!$marker instanceof Marker) {
                continue;
            }

            if ($marker->opens()) {
                $open[] = $marker->key;
                $starts[] = $marker->start;

                continue;
            }

            $runs[] = new Run(implode('/', $open), array_pop($starts) ?? 0, $marker->end);
            array_pop($open);
        }

        return $runs;
    }

    /**
     * @param string $markup The markup.
     *
     * @return list<object> Its component markers in order, Markers; it stops at one that never closes.
     */
    private function markers(string $markup): array
    {
        $markers = [];
        $position = $this->next($markup, 0);

        while ($position >= 0) {
            $marker = $this->marker($markup, $position);

            if ($marker->end < 0) {
                break;
            }

            $markers[] = $marker;
            $position = $this->next($markup, $marker->end);
        }

        return $markers;
    }

    /**
     * @param string $markup The markup.
     * @param int    $start  Where a marker starts.
     *
     * @return Marker The marker there.
     */
    private function marker(string $markup, int $start): Marker
    {
        if (substr($markup, $start, strlen(self::CLOSE)) === self::CLOSE) {
            return new Marker($start, $start + strlen(self::CLOSE), '');
        }

        $end = strpos($markup, '-->', $start);

        if ($end === false) {
            return new Marker($start, -1, '');
        }

        $body = substr($markup, $start + strlen(self::OPEN), $end - $start - strlen(self::OPEN));

        return new Marker($start, $end + 3, explode(' ', $body)[0]);
    }

    /**
     * @param string $markup   The markup.
     * @param int    $position Where to look from.
     *
     * @return int Where the next opening or closing marker starts; -1 for none.
     */
    private function next(string $markup, int $position): int
    {
        $open = strpos($markup, self::OPEN, $position);
        $close = strpos($markup, self::CLOSE, $position);

        if ($open === false) {
            return $close === false
                ? -1
                : $close;
        }

        return $close === false || $open < $close
            ? $open
            : $close;
    }
}
