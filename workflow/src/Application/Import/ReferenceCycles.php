<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\DeferredForeignKey;
use AIC\Workflow\Domain\Import\ImportedForeignKey;
use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function array_fill_keys;
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_pop;
use function array_unique;
use function array_values;
use function implode;
use function in_array;
use function ksort;

/**
 * The order an import's migrations run in (#318), each table after those it references; when tables reference each
 * other in a cycle (#349), one foreign key on it at a time is taken off its table's create, a nullable one first and
 * then by table and column order, until every table is placed. The migration of the later of a deferred key's two
 * tables adds it, and a key whose target was placed first after all goes back into its own create.
 */
final readonly class ReferenceCycles
{
    /**
     * @param array<string, ImportedTable> $tables The tables, by name.
     *
     * @throws WorkflowRefusal When no table is ready and no key closes a cycle, which a finite graph rules out.
     *
     * @return array{list<ImportedTable>, list<string>} The tables in order, and a note on each key a later migration
     *                                                  adds.
     */
    public function order(array $tables): array
    {
        $ordered = [];
        $deferred = [];

        while ($tables !== []) {
            $ready = $this->ready($tables);

            if ($ready === []) {
                $deferredForeignKey = $this->deferrable($tables);
                $deferred[] = $deferredForeignKey;
                $tables[$deferredForeignKey->table] = $this->without($tables[$deferredForeignKey->table], $deferredForeignKey);

                continue;
            }

            foreach ($ready as $name => $importedTable) {
                $ordered[] = $importedTable;
                unset($tables[$name]);
            }
        }

        return $this->closed($ordered, $deferred);
    }

    /**
     * @param array<string, ImportedTable> $tables The tables not placed yet, by name.
     *
     * @return array<string, ImportedTable> Those whose foreign keys reference none of them but themselves.
     */
    private function ready(array $tables): array
    {
        return array_filter($tables, fn (ImportedTable $importedTable): bool => !array_any(
            $importedTable->foreignKeys,
            fn (ImportedForeignKey $importedForeignKey): bool => $this->waits($importedTable, $importedForeignKey, $tables),
        ));
    }

    /**
     * @param ImportedTable                $importedTable      A table.
     * @param ImportedForeignKey           $importedForeignKey One of its keys.
     * @param array<string, ImportedTable> $tables             The tables not placed yet, by name.
     *
     * @return bool Whether the key references another of them.
     */
    private function waits(ImportedTable $importedTable, ImportedForeignKey $importedForeignKey, array $tables): bool
    {
        return $importedForeignKey->table !== $importedTable->name && array_key_exists($importedForeignKey->table, $tables);
    }

    /**
     * @param array<string, ImportedTable> $tables The tables not placed yet, none of them ready, by name.
     *
     * @throws WorkflowRefusal When no key closes a cycle.
     *
     * @return DeferredForeignKey The first key on a cycle, by table and column order, a nullable one before any other.
     */
    private function deferrable(array $tables): DeferredForeignKey
    {
        ksort($tables);
        $candidates = [];

        foreach ($tables as $table) {
            $candidates = [...$candidates, ...$this->candidates($tables, $table)];
        }

        $nullable = array_values(array_filter($candidates, static fn (array $candidate): bool => $candidate[0]));
        $first = $nullable[0] ?? $candidates[0] ?? throw new WorkflowRefusal('no table is ready, yet no foreign key closes a cycle.');

        return $first[1];
    }

    /**
     * @param array<string, ImportedTable> $tables        The tables not placed yet, by name.
     * @param ImportedTable                $importedTable One of them.
     *
     * @return list<array{bool, DeferredForeignKey}> Each of its keys on a cycle, and whether it is nullable.
     */
    private function candidates(array $tables, ImportedTable $importedTable): array
    {
        $candidates = [];

        foreach ($importedTable->foreignKeys as $importedForeignKey) {
            if (!$this->closes($tables, $importedTable, $importedForeignKey)) {
                continue;
            }

            $deferredForeignKey = new DeferredForeignKey($importedTable->name, $importedForeignKey);
            $candidates[] = [$importedTable->nullable($importedForeignKey), $deferredForeignKey];
        }

        return $candidates;
    }

    /**
     * @param array<string, ImportedTable> $tables             The tables not placed yet, by name.
     * @param ImportedTable                $importedTable      One of them.
     * @param ImportedForeignKey           $importedForeignKey One of its keys.
     *
     * @return bool Whether the key references another of them, which leads back to the table.
     */
    private function closes(array $tables, ImportedTable $importedTable, ImportedForeignKey $importedForeignKey): bool
    {
        return $this->waits($importedTable, $importedForeignKey, $tables)
            && $this->reaches($tables, $importedForeignKey->table, $importedTable->name);
    }

    /**
     * @param array<string, ImportedTable> $tables The tables not placed yet, by name.
     * @param string                       $from   One of them.
     * @param string                       $goal   Another.
     *
     * @return bool Whether following foreign keys among them leads from the first to the second.
     */
    private function reaches(array $tables, string $from, string $goal): bool
    {
        $stack = [$from];
        $seen = [$from => true];

        while ($stack !== []) {
            $importedTable = $tables[array_pop($stack)];

            if (in_array($goal, $this->targets($importedTable), true)) {
                return true;
            }

            $next = $this->unseen($tables, $importedTable, $seen);
            $seen += array_fill_keys($next, true);
            $stack = [...$stack, ...$next];
        }

        return false;
    }

    /**
     * @param array<string, ImportedTable> $tables        The tables not placed yet, by name.
     * @param ImportedTable                $importedTable One of them.
     * @param array<string, true>          $seen          The tables a search has reached.
     *
     * @return list<string> The tables not placed yet that its keys reference and the search has not reached.
     */
    private function unseen(array $tables, ImportedTable $importedTable, array $seen): array
    {
        return array_values(array_unique(array_filter(
            $this->targets($importedTable),
            static fn (string $target): bool => array_key_exists($target, $tables) && !array_key_exists($target, $seen),
        )));
    }

    /**
     * @param ImportedTable $importedTable A table.
     *
     * @return list<string> The tables its foreign keys reference.
     */
    private function targets(ImportedTable $importedTable): array
    {
        return array_map(
            static fn (ImportedForeignKey $importedForeignKey): string => $importedForeignKey->table,
            $importedTable->foreignKeys,
        );
    }

    /**
     * @param ImportedTable      $importedTable      A table.
     * @param DeferredForeignKey $deferredForeignKey One of its keys.
     *
     * @return ImportedTable The table without that key.
     */
    private function without(ImportedTable $importedTable, DeferredForeignKey $deferredForeignKey): ImportedTable
    {
        return $importedTable->withForeignKeys(array_values(array_filter(
            $importedTable->foreignKeys,
            static fn (ImportedForeignKey $importedForeignKey): bool => $importedForeignKey !== $deferredForeignKey->importedForeignKey,
        )));
    }

    /**
     * @param list<ImportedTable>      $ordered  The tables in order, without the deferred keys.
     * @param list<DeferredForeignKey> $deferred The deferred keys.
     *
     * @return array{list<ImportedTable>, list<string>} The tables, each deferred key back in its own create when its
     *                                                  target comes first, in its target's migration otherwise; and a
     *                                                  note on each of the latter.
     */
    private function closed(array $ordered, array $deferred): array
    {
        $byName = [];

        foreach ($ordered as $importedTable) {
            $byName[$importedTable->name] = $importedTable;
        }

        $positions = array_flip(array_keys($byName));
        $notes = [];

        foreach ($deferred as $deferredForeignKey) {
            $holder = $deferredForeignKey->table;
            $target = $deferredForeignKey->importedForeignKey->table;

            if ($positions[$target] < $positions[$holder]) {
                $byName[$holder] = $byName[$holder]->withForeignKey($deferredForeignKey->importedForeignKey);

                continue;
            }

            $byName[$target] = $byName[$target]->withClose($deferredForeignKey);
            $notes[] = $this->note($byName[$holder], $deferredForeignKey);
        }

        return [array_values($byName), $notes];
    }

    /**
     * @param ImportedTable      $importedTable      The table that holds the key.
     * @param DeferredForeignKey $deferredForeignKey The key.
     *
     * @return string What the import did with it, and, for a key with a NOT NULL column, what that means for the rows.
     */
    private function note(ImportedTable $importedTable, DeferredForeignKey $deferredForeignKey): string
    {
        $importedForeignKey = $deferredForeignKey->importedForeignKey;
        $note = $importedTable->name . ': the foreign key on ' . implode(', ', $importedForeignKey->columns) . ' is added by '
            . $importedForeignKey->table . "'s migration, as the key closes a reference cycle";

        return $importedTable->nullable($importedForeignKey)
            ? $note
            : $note . '; no key on that cycle may be null, so its tables take no row until one of its keys may';
    }
}
