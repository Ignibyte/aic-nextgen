<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Exception\UnsupportedSql;

use function array_key_exists;
use function array_keys;

/**
 * What a schema dump states, gathered statement by statement (#318): its tables, the sequences a serial column
 * owns, the findings that stop an import (by table; an empty table for a statement about none) and the notes.
 */
final class DumpFacts
{
    /** @var array<string, ImportedTable> */
    private array $tables = [];

    /** @var array<string, string> */
    private array $sequences = [];

    /** @var list<array{string, string}> */
    private array $findings = [];

    /** @var list<string> */
    private array $notes = [];

    /**
     * @param ImportedTable $importedTable A table, new or changed.
     */
    public function put(ImportedTable $importedTable): void
    {
        $this->tables[$importedTable->name] = $importedTable;
    }

    /**
     * @param string $name A table.
     *
     * @throws UnsupportedSql When the dump has not created it.
     *
     * @return ImportedTable It.
     */
    public function table(string $name): ImportedTable
    {
        return $this->tables[$name] ?? throw new UnsupportedSql('the table ' . $name . ' is not created before this');
    }

    /**
     * @return array<string, ImportedTable> Every table, by name, in the dump's order.
     */
    public function tables(): array
    {
        return $this->tables;
    }

    /**
     * @param string $sequence A sequence the dump creates.
     */
    public function sequence(string $sequence): void
    {
        $this->sequences[$sequence] = '';
    }

    /**
     * @param string $sequence A sequence.
     * @param string $owner    The `<table>.<column>` that owns it.
     *
     * @throws UnsupportedSql When the dump has not created the sequence.
     */
    public function own(string $sequence, string $owner): void
    {
        if (!array_key_exists($sequence, $this->sequences)) {
            throw new UnsupportedSql('the sequence ' . $sequence . ' is not created before this');
        }

        $this->sequences[$sequence] = $owner;
    }

    /**
     * @param string $sequence A sequence.
     *
     * @return string The `<table>.<column>` that owns it; empty when none does.
     */
    public function owner(string $sequence): string
    {
        return $this->sequences[$sequence] ?? '';
    }

    /**
     * @return list<string> The sequences no column owns.
     */
    public function standaloneSequences(): array
    {
        return array_keys($this->sequences, '', true);
    }

    /**
     * @param string $table   The table the finding is about; empty for none.
     * @param string $finding What Schema cannot declare.
     */
    public function find(string $table, string $finding): void
    {
        $this->findings[] = [$table, $finding];
    }

    /**
     * @return list<array{string, string}> Each finding's table and text.
     */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * @param string $note A translation made, or something left out that is not schema.
     */
    public function note(string $note): void
    {
        $this->notes[] = $note;
    }

    /**
     * @return list<string> The notes.
     */
    public function notes(): array
    {
        return $this->notes;
    }
}
