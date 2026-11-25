<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\DumpToken;
use AIC\Workflow\Exception\UnsupportedSql;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;

use function array_key_exists;
use function array_map;
use function array_slice;
use function count;
use function implode;
use function strtoupper;

/**
 * A schema dump read statement by statement (#318): each goes to its reader; settings, privileges, owners and
 * extension comments are a deploy's and go; anything else is a finding, on the table it is about when it names
 * one.
 */
final readonly class DumpStatements
{
    /** The first words of each statement that is a deploy's, or sets a sequence where its rows are (#344). */
    private const array IGNORED = [
        ['set'],
        ['select', 'pg_catalog', '.', 'set_config'],
        ['select', 'pg_catalog', '.', 'setval'],
        ['grant'],
        ['revoke'],
        ['comment', 'on', 'extension'],
        ['alter', 'default', 'privileges'],
    ];

    /**
     * @param DumpLexer   $dumpLexer   Splits the dump.
     * @param TableReader $tableReader Reads `CREATE TABLE`.
     * @param AlterReader $alterReader Reads `ALTER TABLE`.
     * @param IndexReader $indexReader Reads `CREATE INDEX`.
     */
    public function __construct(
        private DumpLexer $dumpLexer = new DumpLexer(),
        private TableReader $tableReader = new TableReader(),
        private AlterReader $alterReader = new AlterReader(),
        private IndexReader $indexReader = new IndexReader(),
    ) {
    }

    /**
     * @param string $sql A `pg_dump --schema-only`.
     *
     * @throws WorkflowRefusal When the dump holds a character pg_dump does not write.
     *
     * @return DumpFacts What it states.
     */
    public function read(string $sql): DumpFacts
    {
        $dumpFacts = new DumpFacts();

        foreach ($this->dumpLexer->statements($sql) as $tokens) {
            try {
                $this->statement(new TokenCursor($tokens), $tokens, $dumpFacts);
            } catch (UnsupportedSql $unsupportedSql) {
                $dumpFacts->find($this->subject($tokens), $unsupportedSql->getMessage() . ', in: ' . $this->head($tokens));
            }
        }

        foreach ($dumpFacts->standaloneSequences() as $sequence) {
            $dumpFacts->find('', 'the sequence ' . $sequence . ' belongs to no column, and Schema declares no standalone sequence');
        }

        return $dumpFacts;
    }

    /**
     * @param TokenCursor     $tokenCursor A statement.
     * @param list<DumpToken> $tokens      Its tokens.
     * @param DumpFacts       $dumpFacts   What the dump has said so far.
     *
     * @throws UnsupportedSql When the statement has no Schema declaration.
     */
    private function statement(TokenCursor $tokenCursor, array $tokens, DumpFacts $dumpFacts): void
    {
        foreach ($this->readers($dumpFacts) as [$words, $reader]) {
            if ($tokenCursor->accept(...$words)) {
                $reader($tokenCursor);

                return;
            }
        }

        $this->other($tokens);
    }

    /**
     * @param DumpFacts $dumpFacts What the dump has said so far.
     *
     * @return list<array{list<string>, Closure(TokenCursor): void}> The statements read, by their first words.
     */
    private function readers(DumpFacts $dumpFacts): array
    {
        $ignored = static function (): void {
        };

        return [
            ...array_map(static fn (array $words): array => [$words, $ignored], self::IGNORED),
            [['copy'], fn (TokenCursor $tokenCursor) => $this->copied($tokenCursor, $dumpFacts)],
            [['comment', 'on'], fn (TokenCursor $tokenCursor) => $this->comment($tokenCursor, $dumpFacts)],
            [['create', 'extension'], fn (TokenCursor $tokenCursor) => $this->extension($tokenCursor, $dumpFacts)],
            [['create', 'table'], fn (TokenCursor $tokenCursor) => $this->table($tokenCursor, false, $dumpFacts)],
            [['create', 'unlogged', 'table'], fn (TokenCursor $tokenCursor) => $this->table($tokenCursor, true, $dumpFacts)],
            [['create', 'sequence'], static fn (TokenCursor $tokenCursor) => $dumpFacts->sequence('public.' . $tokenCursor->table())],
            [['alter', 'sequence'], fn (TokenCursor $tokenCursor) => $this->owned($tokenCursor, $dumpFacts)],
            [['alter', 'table'], fn (TokenCursor $tokenCursor) => $this->alterReader->read($tokenCursor, $dumpFacts)],
            [['create', 'index'], fn (TokenCursor $tokenCursor) => $this->indexReader->read($tokenCursor, false, $dumpFacts)],
            [['create', 'unique', 'index'], fn (TokenCursor $tokenCursor) => $this->indexReader->read($tokenCursor, true, $dumpFacts)],
        ];
    }

    /**
     * @param TokenCursor $tokenCursor After `CREATE [UNLOGGED] TABLE`.
     * @param bool        $unlogged    Whether it said `UNLOGGED`.
     * @param DumpFacts   $dumpFacts   Takes the table and a finding for each part Schema cannot declare.
     *
     * @throws UnsupportedSql When its name or options have no Schema declaration.
     */
    private function table(TokenCursor $tokenCursor, bool $unlogged, DumpFacts $dumpFacts): void
    {
        [$importedTable, $findings] = $this->tableReader->read($tokenCursor, $unlogged);
        $dumpFacts->put($importedTable);

        foreach ($findings as $finding) {
            $dumpFacts->find($importedTable->name, $finding . ', in: CREATE TABLE ' . $importedTable->name);
        }
    }

    /**
     * @param TokenCursor $tokenCursor After `CREATE EXTENSION`.
     * @param DumpFacts   $dumpFacts   For the note.
     *
     * @throws UnsupportedSql When no name follows.
     */
    private function extension(TokenCursor $tokenCursor, DumpFacts $dumpFacts): void
    {
        $tokenCursor->accept('if', 'not', 'exists');
        $name = $tokenCursor->name();

        if ($name === 'pg_trgm') {
            return;
        }

        $declare = "\$schema->extension('" . $name . "')";
        $dumpFacts->note('the extension ' . $name . ' is not declared: a migration that needs it says ' . $declare . '.');
    }

    /**
     * @param TokenCursor $tokenCursor After `COPY`; the lexer has left its rows out.
     * @param DumpFacts   $dumpFacts   Takes the note.
     *
     * @throws UnsupportedSql When it names no table.
     */
    private function copied(TokenCursor $tokenCursor, DumpFacts $dumpFacts): void
    {
        $dumpFacts->note('the rows of ' . $tokenCursor->table() . ' (COPY) are data, not schema: they are not imported.');
    }

    /**
     * @param TokenCursor $tokenCursor After `COMMENT ON`.
     * @param DumpFacts   $dumpFacts   Takes the note.
     */
    private function comment(TokenCursor $tokenCursor, DumpFacts $dumpFacts): void
    {
        $dumpFacts->note('COMMENT ON ' . $tokenCursor->rest() . ' is not kept.');
    }

    /**
     * @param TokenCursor $tokenCursor After `ALTER SEQUENCE`.
     * @param DumpFacts   $dumpFacts   The sequences.
     *
     * @throws UnsupportedSql When it does anything but give the sequence an owner or an owning role.
     */
    private function owned(TokenCursor $tokenCursor, DumpFacts $dumpFacts): void
    {
        $sequence = 'public.' . $tokenCursor->table();

        if ($tokenCursor->accept('owner', 'to')) {
            return;
        }

        $tokenCursor->expect('owned', 'by');
        $owner = $tokenCursor->name() . '.' . $this->rest($tokenCursor);
        $dumpFacts->own($sequence, $owner);
    }

    /**
     * @param TokenCursor $tokenCursor After `OWNED BY <schema>`.
     *
     * @throws UnsupportedSql When it is not `.<table>.<column>`.
     *
     * @return string The `<table>.<column>`.
     */
    private function rest(TokenCursor $tokenCursor): string
    {
        $tokenCursor->expect('.');
        $table = $tokenCursor->name();
        $tokenCursor->expect('.');
        $column = $tokenCursor->name();
        $tokenCursor->end();

        return $table . '.' . $column;
    }

    /**
     * @param list<DumpToken> $tokens A statement no reader takes.
     *
     * @throws UnsupportedSql Unless it only gives something an owning role.
     */
    private function other(array $tokens): void
    {
        $count = count($tokens);

        if ($count < 3 || !$tokens[$count - 3]->isWord('owner') || !$tokens[$count - 2]->isWord('to')) {
            $words = implode(' ', [$tokens[0]->text ?? '', $tokens[1]->text ?? '']);

            throw new UnsupportedSql(strtoupper($words) . ' has no Schema declaration');
        }
    }

    /**
     * @param list<DumpToken> $tokens A statement.
     *
     * @return string The first table it names, `public.<name>`; empty when it names none.
     */
    private function subject(array $tokens): string
    {
        foreach ($tokens as $index => $dumpToken) {
            if (
                $dumpToken->isWord('public')
                && array_key_exists($index + 2, $tokens)
                && $tokens[$index + 1]->isSymbol('.')
            ) {
                return $tokens[$index + 2]->text;
            }
        }

        return '';
    }

    /**
     * @param list<DumpToken> $tokens A statement.
     *
     * @return string Its first words, for a finding.
     */
    private function head(array $tokens): string
    {
        $texts = [];

        foreach (array_slice($tokens, 0, 10) as $dumpToken) {
            $texts[] = $dumpToken->text;
        }

        return implode(' ', $texts) . (count($tokens) > 10 ? ' …' : '');
    }
}
