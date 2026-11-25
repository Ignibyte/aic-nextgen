<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\ImportedForeignKey;
use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function in_array;

/**
 * Which of a dump's tables an import writes, and in what order (#318): those `--tables` names (every table without
 * it), each ready to write, after the tables its foreign keys reference. Every finding on a chosen table, and every
 * statement about no table when the whole dump is imported, refuses the import, listed one a line; a statement about
 * no table under `--tables` is a note.
 */
final readonly class ImportPlanner
{
    /**
     * @param TablePreparer $tablePreparer Readies each table.
     */
    public function __construct(
        private TablePreparer $tablePreparer = new TablePreparer(),
    ) {
    }

    /**
     * @param DumpFacts    $dumpFacts What the dump states; the notes are added to it.
     * @param list<string> $selection The tables `--tables` names; empty for every table.
     * @param list<string> $existing  The tables the modules' migrations already create.
     *
     * @throws WorkflowRefusal When `--tables` names a table the dump lacks, or a chosen table has a finding.
     *
     * @return list<ImportedTable> The tables, in the order their migrations run.
     */
    public function plan(DumpFacts $dumpFacts, array $selection, array $existing): array
    {
        $chosen = $this->chosen($dumpFacts, $selection);
        [$prepared, $findings] = $this->prepared($dumpFacts, $chosen, $existing);
        [$ordered, $cycle] = $this->ordered($prepared);
        $findings = [...$this->findings($dumpFacts, $chosen, $selection !== []), ...$findings];
        $findings = $cycle === []
            ? $findings
            : [...$findings, implode(', ', $cycle) . ' reference each other in a cycle, which one migration each cannot create'];

        if ($findings !== []) {
            throw new WorkflowRefusal(
                'the dump states ' . count($findings) . " thing(s) AIC's Schema cannot declare, so nothing was written:\n- "
                    . implode("\n- ", $findings),
            );
        }

        return $ordered;
    }

    /**
     * @param DumpFacts    $dumpFacts What the dump states; takes the notes.
     * @param list<string> $chosen    The tables chosen.
     * @param list<string> $existing  The tables the modules create.
     *
     * @return array{array<string, ImportedTable>, list<string>} Each chosen table the dump creates, ready, by name; and
     *                                                           the findings on their keys and foreign keys.
     */
    private function prepared(DumpFacts $dumpFacts, array $chosen, array $existing): array
    {
        $tables = $dumpFacts->tables();
        $prepared = [];
        $findings = [];

        foreach ($chosen as $name) {
            if (!array_key_exists($name, $tables)) {
                continue;
            }

            [$prepared[$name], $more] = $this->tablePreparer->prepare($tables[$name], $dumpFacts);
            $more = array_map(static fn (string $finding): string => $name . ': ' . $finding, $more);
            $findings = [...$findings, ...$more, ...$this->targets($prepared[$name], [...$chosen, ...$existing])];
        }

        return [$prepared, $findings];
    }

    /**
     * @param DumpFacts    $dumpFacts What the dump states.
     * @param list<string> $selection The tables named; empty for every table.
     *
     * @throws WorkflowRefusal When a named table is not in the dump.
     *
     * @return list<string> The tables chosen.
     */
    private function chosen(DumpFacts $dumpFacts, array $selection): array
    {
        $subjects = array_map(static fn (array $finding): string => $finding[0], $dumpFacts->findings());
        $known = [...array_keys($dumpFacts->tables()), ...$subjects];

        foreach ($selection as $name) {
            if (!in_array($name, $known, true)) {
                throw new WorkflowRefusal('--tables names ' . $name . ', which the dump does not create.');
            }
        }

        return $selection === []
            ? array_keys($dumpFacts->tables())
            : $selection;
    }

    /**
     * @param DumpFacts    $dumpFacts What the dump states.
     * @param list<string> $chosen    The tables chosen.
     * @param bool         $partial   Whether `--tables` chose them.
     *
     * @return list<string> The findings that refuse the import.
     */
    private function findings(DumpFacts $dumpFacts, array $chosen, bool $partial): array
    {
        $findings = [];

        foreach ($dumpFacts->findings() as [$table, $finding]) {
            $text = $this->finding($dumpFacts, $chosen, $partial, $table, $finding);
            $findings = $text === ''
                ? $findings
                : [...$findings, $text];
        }

        return $findings;
    }

    /**
     * @param DumpFacts    $dumpFacts What the dump states; takes the note.
     * @param list<string> $chosen    The tables chosen.
     * @param bool         $partial   Whether `--tables` chose them.
     * @param string       $table     The table a finding is about; empty for none.
     * @param string       $finding   The finding.
     *
     * @return string The finding as it refuses the import; empty when it does not, as it is about a table not chosen
     *                or, under `--tables`, about no table (a note then).
     */
    private function finding(DumpFacts $dumpFacts, array $chosen, bool $partial, string $table, string $finding): string
    {
        $isTable = array_key_exists($table, $dumpFacts->tables());

        if ($this->counts($table, $chosen, $isTable, $partial)) {
            return $table === ''
                ? $finding
                : $table . ': ' . $finding;
        }

        if (!$isTable) {
            $dumpFacts->note('not imported, as it is about no chosen table: ' . $finding);
        }

        return '';
    }

    /**
     * @param string       $table   The table a finding is about; empty for none.
     * @param list<string> $chosen  The tables chosen.
     * @param bool         $isTable Whether the dump creates a table of that name.
     * @param bool         $partial Whether `--tables` chose them.
     *
     * @return bool Whether the finding refuses the import: it is about a chosen table, or about no table of a whole
     *              dump.
     */
    private function counts(string $table, array $chosen, bool $isTable, bool $partial): bool
    {
        return in_array($table, $chosen, true) || (!$isTable && !$partial);
    }

    /**
     * @param ImportedTable $importedTable A chosen table.
     * @param list<string>  $known         The tables the import or a module creates.
     *
     * @return list<string> Each foreign key that references a table neither the import nor a module creates.
     */
    private function targets(ImportedTable $importedTable, array $known): array
    {
        $findings = [];

        foreach ($importedTable->foreignKeys as $importedForeignKey) {
            if (in_array($importedForeignKey->table, $known, true)) {
                continue;
            }

            $findings[] = $importedTable->name . ': the foreign key on ' . implode(', ', $importedForeignKey->columns) . ' references '
                . $importedForeignKey->table . ", which neither the import nor a module's migration creates";
        }

        return $findings;
    }

    /**
     * @param array<string, ImportedTable> $tables The tables, by name.
     *
     * @return array{list<ImportedTable>, list<string>} The tables each after those it references, and the tables left
     *                                                  in a cycle (none when every one is placed).
     */
    private function ordered(array $tables): array
    {
        $ordered = [];

        while ($tables !== []) {
            $ready = $this->ready($tables);

            if ($ready === []) {
                return [$ordered, array_keys($tables)];
            }

            foreach ($ready as $name => $importedTable) {
                $ordered[] = $importedTable;
                unset($tables[$name]);
            }
        }

        return [$ordered, []];
    }

    /**
     * @param array<string, ImportedTable> $tables The tables not placed yet, by name.
     *
     * @return array<string, ImportedTable> Those whose foreign keys reference none of them but themselves.
     */
    private function ready(array $tables): array
    {
        return array_filter($tables, static fn (ImportedTable $importedTable): bool => !array_any(
            $importedTable->foreignKeys,
            static fn (ImportedForeignKey $importedForeignKey): bool => $importedForeignKey->table !== $importedTable->name
                && array_key_exists($importedForeignKey->table, $tables),
        ));
    }
}
