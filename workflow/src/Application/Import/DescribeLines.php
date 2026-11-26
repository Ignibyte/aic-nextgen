<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Exception\UnsupportedSql;

use function array_map;
use function count;
use function implode;

/**
 * An imported table's `describe()` (#318): pg_trgm when an index needs it, the create, the helpers' calls, then the
 * foreign keys of earlier tables that reference it in a cycle (#349), one `$schema->alter()` chain per table that holds
 * such a key, each key a `references()` line. A migration's declarations run in order, so the alter follows the create;
 * the keys sit here, not in a helper, so they take none of PHPMD's 15 methods.
 */
final readonly class DescribeLines
{
    private const string CHAIN = '            ->';

    /**
     * @param PhpLiteral $phpLiteral Writes the names.
     */
    public function __construct(
        private PhpLiteral $phpLiteral = new PhpLiteral(),
    ) {
    }

    /**
     * @param ImportedTable               $importedTable The table.
     * @param list<array{string, string}> $helpers       Each helper's call and PHP.
     *
     * @throws UnsupportedSql When the keys it adds to earlier tables pass 12 lines, which would take `describe()` past
     *                        PHPCS's 20.
     *
     * @return string The body.
     */
    public function body(ImportedTable $importedTable, array $helpers): string
    {
        $lines = $importedTable->needsTrigrams()
            ? ["        \$schema->extension('pg_trgm');"]
            : [];
        $unlogged = $importedTable->unlogged
            ? '->unlogged()'
            : '';
        $lines[] = "        \$tableBuilder = \$schema->create('" . $importedTable->name . "')" . $unlogged . ';';
        $references = $this->chains($importedTable);

        if (count($references) > 12) {
            throw new UnsupportedSql(
                'the foreign keys that close its reference cycles are too many for its migration (' . count($references) . ' lines)',
            );
        }

        return implode("\n", [...$lines, ...array_map(static fn (array $helper): string => $helper[0], $helpers), ...$references]);
    }

    /**
     * @param ImportedTable $importedTable The table.
     *
     * @return list<string> Per table that holds a key, `$schema->alter()` and a line per key.
     */
    private function chains(ImportedTable $importedTable): array
    {
        $chains = [];

        foreach ($importedTable->closes as $deferredForeignKey) {
            $importedForeignKey = $deferredForeignKey->importedForeignKey;
            $chains[$deferredForeignKey->table][] = self::CHAIN . 'references(' . $this->names($importedForeignKey->columns) . ', '
                . $this->phpLiteral->string($importedForeignKey->table) . ', ' . $this->names($importedForeignKey->foreignColumns)
                . ', OnDelete::' . $importedForeignKey->onDelete . ')';
        }

        $lines = [];

        foreach ($chains as $table => $chain) {
            $chain[count($chain) - 1] .= ';';
            $lines = [...$lines, '        $schema->alter(' . $this->phpLiteral->string($table) . ')', ...$chain];
        }

        return $lines;
    }

    /**
     * @param list<string> $names Column names.
     *
     * @return string Them as a PHP list.
     */
    private function names(array $names): string
    {
        return '[' . implode(', ', array_map($this->phpLiteral->string(...), $names)) . ']';
    }
}
