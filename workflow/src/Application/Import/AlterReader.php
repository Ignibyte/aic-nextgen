<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\ColumnKey;
use AIC\Workflow\Domain\Import\ImportedForeignKey;
use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Exception\UnsupportedSql;

use function count;

/**
 * An `ALTER TABLE` of a schema dump (#318): a primary key, a unique constraint, a foreign key or a check added, an
 * identity, or a serial's `nextval` default. `OWNER TO` is a deploy's business and is left out.
 */
final readonly class AlterReader
{
    private const array ON_DELETE = ['Cascade' => ['cascade'], 'Restrict' => ['restrict'], 'SetNull' => ['set', 'null']];

    /**
     * @param TableReader $tableReader Reads a check.
     */
    public function __construct(
        private TableReader $tableReader = new TableReader(),
    ) {
    }

    /**
     * @param TokenCursor $tokenCursor After `ALTER TABLE`.
     * @param DumpFacts   $dumpFacts   What the dump has said so far; the table is changed in it.
     *
     * @throws UnsupportedSql When the change has no Schema declaration.
     */
    public function read(TokenCursor $tokenCursor, DumpFacts $dumpFacts): void
    {
        $tokenCursor->accept('only');
        $importedTable = $dumpFacts->table($tokenCursor->table());

        if ($tokenCursor->accept('owner', 'to')) {
            return;
        }

        $unsupported = 'ALTER TABLE ' . $importedTable->name . ' ' . $tokenCursor->rest() . ' has no Schema declaration';
        $dumpFacts->put(match (true) {
            $tokenCursor->accept('alter', 'column') => $this->column($tokenCursor, $importedTable, $dumpFacts),
            $tokenCursor->accept('add', 'constraint') => $this->constraint($tokenCursor, $importedTable, $dumpFacts),
            default => throw new UnsupportedSql($unsupported),
        });
    }

    /**
     * @param TokenCursor   $tokenCursor   After `ALTER COLUMN`.
     * @param ImportedTable $importedTable The table.
     * @param DumpFacts     $dumpFacts     The sequences a serial column owns.
     *
     * @throws UnsupportedSql When the column is not made an identity or a serial.
     *
     * @return ImportedTable The table with the column's key.
     */
    private function column(TokenCursor $tokenCursor, ImportedTable $importedTable, DumpFacts $dumpFacts): ImportedTable
    {
        $name = $tokenCursor->name();
        $importedColumn = $importedTable->column($name) ?? throw new UnsupportedSql('the table has no column ' . $name);
        $owner = $importedTable->name . '.' . $name;
        $columnKey = match (true) {
            $tokenCursor->accept('add', 'generated', 'always', 'as', 'identity') => ColumnKey::Identity,
            $tokenCursor->accept('add', 'generated', 'by', 'default', 'as', 'identity') => ColumnKey::IdentityByDefault,
            $tokenCursor->accept('set', 'default', 'nextval') => $this->serial($tokenCursor, $owner, $dumpFacts),
            default => throw new UnsupportedSql('ALTER COLUMN ' . $name . ' ' . $tokenCursor->rest() . ' has no Schema declaration'),
        };

        if ($columnKey !== ColumnKey::Serial) {
            $tokenCursor->group();
        }

        $tokenCursor->end();

        return $importedTable->withColumn($importedColumn->withKey($columnKey));
    }

    /**
     * @param TokenCursor $tokenCursor After `SET DEFAULT nextval`.
     * @param string      $column      The `<table>.<column>`.
     * @param DumpFacts   $dumpFacts   The sequences' owners.
     *
     * @throws UnsupportedSql When the sequence is not owned by the column.
     *
     * @return ColumnKey A serial.
     */
    private function serial(TokenCursor $tokenCursor, string $column, DumpFacts $dumpFacts): ColumnKey
    {
        $group = $tokenCursor->group();
        $sequence = $group->next()->text;
        $group->expect('::', 'regclass');
        $group->end();

        return $dumpFacts->owner($sequence) === 'public.' . $column
            ? ColumnKey::Serial
            : throw new UnsupportedSql('the default nextval(' . $sequence . ') draws on a sequence ' . $column . ' does not own');
    }

    /**
     * @param TokenCursor   $tokenCursor   After `ADD CONSTRAINT`.
     * @param ImportedTable $importedTable The table.
     * @param DumpFacts     $dumpFacts     For the notes.
     *
     * @throws UnsupportedSql When it is no primary key, unique constraint, foreign key or check Schema declares.
     *
     * @return ImportedTable The table with it.
     */
    private function constraint(
        TokenCursor $tokenCursor,
        ImportedTable $importedTable,
        DumpFacts $dumpFacts,
    ): ImportedTable {
        $next = $tokenCursor->peek(1);

        if ($next !== null && $next->isWord('check')) {
            return $importedTable->withChecks([...$importedTable->checks, $this->tableReader->check($tokenCursor, $importedTable->name)]);
        }

        $tokenCursor->name();
        $changed = match (true) {
            $tokenCursor->accept('primary', 'key') => $importedTable->withPrimary($tokenCursor->names()),
            $tokenCursor->accept('unique', 'nulls') => throw new UnsupportedSql('UNIQUE NULLS NOT DISTINCT has no Schema declaration'),
            $tokenCursor->accept('unique') => $importedTable->withUnique($tokenCursor->names()),
            $tokenCursor->accept('foreign', 'key') => $this->withForeignKey($tokenCursor, $importedTable, $dumpFacts),
            default => throw new UnsupportedSql('the constraint "' . $tokenCursor->rest() . '" has no Schema declaration'),
        };
        $tokenCursor->end();

        return $changed;
    }

    /**
     * @param TokenCursor   $tokenCursor   After `FOREIGN KEY`.
     * @param ImportedTable $importedTable The table.
     * @param DumpFacts     $dumpFacts     For the note when it has no `ON DELETE`.
     *
     * @throws UnsupportedSql When the key has no Schema declaration.
     *
     * @return ImportedTable The table with it.
     */
    private function withForeignKey(
        TokenCursor $tokenCursor,
        ImportedTable $importedTable,
        DumpFacts $dumpFacts,
    ): ImportedTable {
        return $importedTable->withForeignKey($this->foreignKey($tokenCursor, $importedTable->name, $dumpFacts));
    }

    /**
     * @param TokenCursor $tokenCursor After `FOREIGN KEY`.
     * @param string      $table       The table.
     * @param DumpFacts   $dumpFacts   For the note when it has no `ON DELETE`.
     *
     * @throws UnsupportedSql When it spans columns, acts on update, is deferrable or matches other than simply.
     *
     * @return ImportedForeignKey The foreign key.
     */
    private function foreignKey(TokenCursor $tokenCursor, string $table, DumpFacts $dumpFacts): ImportedForeignKey
    {
        $columns = $tokenCursor->names();
        $tokenCursor->expect('references');
        $foreignTable = $tokenCursor->table();
        $foreignColumns = $tokenCursor->names();

        if (count($columns) !== 1 || count($foreignColumns) !== 1) {
            throw new UnsupportedSql('a foreign key over more than one column has no Schema declaration');
        }

        if ($tokenCursor->accept('on', 'update')) {
            throw new UnsupportedSql('ON UPDATE ' . $tokenCursor->rest() . ' has no Schema declaration');
        }

        $onDelete = $this->onDelete($tokenCursor);

        if ($onDelete === '') {
            $dumpFacts->note($table . '.' . $columns[0] . ' has no ON DELETE (NO ACTION); it becomes OnDelete::Restrict.');
            $onDelete = 'Restrict';
        }

        return new ImportedForeignKey($columns[0], $foreignTable, $foreignColumns[0], $onDelete);
    }

    /**
     * @param TokenCursor $tokenCursor After the referenced column.
     *
     * @throws UnsupportedSql When the action is SET DEFAULT or NO ACTION spelled out, or another clause follows.
     *
     * @return string The `OnDelete` case; empty when the key says nothing.
     */
    private function onDelete(TokenCursor $tokenCursor): string
    {
        if (!$tokenCursor->accept('on', 'delete')) {
            return '';
        }

        foreach (self::ON_DELETE as $case => $words) {
            if ($tokenCursor->accept(...$words)) {
                return $case;
            }
        }

        throw new UnsupportedSql('ON DELETE ' . $tokenCursor->rest() . ' has no Schema declaration');
    }
}
