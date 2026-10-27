<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * A table a module file names (#185): the name as written, empty when it is
 * not a literal, its line, and what names it.
 */
final readonly class TableName
{
    /**
     * @param string $table The table; empty when the code computes it.
     * @param int    $line  The line that names it.
     * @param string $what  What names it: `a migration`, `an alteration` or `an entity type`.
     */
    public function __construct(
        public string $table,
        public int $line,
        public string $what,
    ) {
    }
}
