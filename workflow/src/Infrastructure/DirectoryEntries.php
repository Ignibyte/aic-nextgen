<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Checked filesystem entries from native recursive iteration, in traversal order.
 */
trait DirectoryEntries
{
    /**
     * @param RecursiveIteratorIterator<RecursiveDirectoryIterator> $iterator The configured traversal.
     *
     * @return list<SplFileInfo> Its file entries, retaining the selected traversal order.
     */
    private static function directoryEntries(RecursiveIteratorIterator $iterator): array
    {
        $entries = [];

        for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
            $current = [$iterator->current()];

            if (!$current[0] instanceof SplFileInfo) {
                continue;
            }

            $entries[] = $current[0];
        }

        return $entries;
    }
}
