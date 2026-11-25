<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\CodeNode;
use AIC\Workflow\Domain\Import\CodeValue;
use AIC\Workflow\Domain\Import\ColumnKey;
use AIC\Workflow\Domain\Import\DumpToken;
use AIC\Workflow\Domain\Import\DumpTokenKind;
use AIC\Workflow\Domain\Import\ImportedCheck;
use AIC\Workflow\Domain\Import\ImportedColumn;
use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Exception\UnsupportedSql;

use function str_starts_with;
use function strlen;
use function substr;

/**
 * A `CREATE [UNLOGGED] TABLE` of a schema dump (#318): its columns, with their types, nullability, defaults and
 * generated terms, and the checks pg_dump writes inside it.
 */
final readonly class TableReader
{
    /**
     * @param ColumnTypesReader $columnTypesReader Reads the column types.
     * @param PhpLiteral        $phpLiteral        Writes a jsonb default's text.
     * @param ExpressionReader  $expressionReader  Reads the defaults, generated terms and checks.
     */
    public function __construct(
        private ColumnTypesReader $columnTypesReader = new ColumnTypesReader(),
        private PhpLiteral $phpLiteral = new PhpLiteral(),
        private ExpressionReader $expressionReader = new ExpressionReader(),
    ) {
    }

    /**
     * @param TokenCursor $tokenCursor After `CREATE [UNLOGGED] TABLE`.
     * @param bool        $unlogged    Whether it said `UNLOGGED`.
     *
     * @throws UnsupportedSql When the name or an option after the columns has no Schema declaration.
     *
     * @return array{ImportedTable, list<string>} The table without any column or check Schema cannot declare, and
     *                                            why each such one cannot be.
     */
    public function read(TokenCursor $tokenCursor, bool $unlogged): array
    {
        $name = $tokenCursor->table();
        $items = $tokenCursor->group()->items();
        $tokenCursor->end();
        [$columns, $checks, $findings] = [[], [], []];

        foreach ($items as $item) {
            $part = $this->item($item, $name);

            if ($part instanceof ImportedColumn) {
                $columns[] = $part;
                continue;
            }

            if ($part instanceof ImportedCheck) {
                $checks[] = $part;
                continue;
            }

            $findings[] = $part;
        }

        return [new ImportedTable($name, $unlogged, $columns, [], [], $checks), $findings];
    }

    /**
     * @param TokenCursor $tokenCursor After `CONSTRAINT`.
     * @param string      $table       The table.
     *
     * @throws UnsupportedSql When the constraint is not a check Schema can state.
     *
     * @return ImportedCheck The check.
     */
    public function check(TokenCursor $tokenCursor, string $table): ImportedCheck
    {
        $name = $tokenCursor->name();
        $tokenCursor->expect('check');
        $predicate = $this->expressionReader->predicate($tokenCursor->group());

        if ($tokenCursor->accept('not', 'valid')) {
            throw new UnsupportedSql('the check ' . $name . ' is NOT VALID, which Schema does not declare');
        }

        $tokenCursor->end();
        $prefix = $table . '_';

        return new ImportedCheck(str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name, $predicate);
    }

    /**
     * @param TokenCursor $tokenCursor A column's or a constraint's tokens.
     * @param string      $table       The table.
     *
     * @return ImportedColumn|ImportedCheck|string The column, the check, or why it has no Schema declaration.
     */
    private function item(TokenCursor $tokenCursor, string $table): ImportedColumn|ImportedCheck|string
    {
        try {
            return $tokenCursor->accept('constraint')
                ? $this->check($tokenCursor, $table)
                : $this->column($tokenCursor);
        } catch (UnsupportedSql $unsupportedSql) {
            return $unsupportedSql->getMessage();
        }
    }

    /**
     * @param TokenCursor $tokenCursor A column's tokens.
     *
     * @throws UnsupportedSql When its type or a clause has no Schema declaration.
     *
     * @return ImportedColumn The column.
     */
    private function column(TokenCursor $tokenCursor): ImportedColumn
    {
        $importedColumn = new ImportedColumn($tokenCursor->name(), $this->columnTypesReader->read($tokenCursor), true);

        while (!$tokenCursor->atEnd()) {
            $importedColumn = $this->clause($tokenCursor, $importedColumn);
        }

        return $importedColumn;
    }

    /**
     * @param TokenCursor    $tokenCursor    At a clause of the column.
     * @param ImportedColumn $importedColumn The column so far.
     *
     * @throws UnsupportedSql When the clause has no Schema declaration.
     *
     * @return ImportedColumn The column with the clause, read.
     */
    private function clause(TokenCursor $tokenCursor, ImportedColumn $importedColumn): ImportedColumn
    {
        return match (true) {
            $tokenCursor->accept('not', 'null') => $importedColumn->notNull(),
            $tokenCursor->accept('null') => $importedColumn,
            $tokenCursor->accept('default', 'gen_random_uuid', '(', ')') => $importedColumn->withKey(ColumnKey::UuidKey),
            $tokenCursor->accept('default') => $importedColumn->withDefault($this->defaultTerm($tokenCursor)),
            $tokenCursor->accept('generated', 'always', 'as') => $this->generated($tokenCursor, $importedColumn),
            default => throw new UnsupportedSql(
                'the column ' . $importedColumn->name . ' says "' . $tokenCursor->rest() . '", which Schema does not declare',
            ),
        };
    }

    /**
     * @param TokenCursor $tokenCursor After `DEFAULT`.
     *
     * @throws UnsupportedSql When the term has something Schema cannot state.
     *
     * @return CodeNode The default: `now()` for `CURRENT_TIMESTAMP` or `now()`, and a jsonb literal for a text cast to
     *                  jsonb, from `Schema::defaults()` (#339); any other term as the expressions state it.
     */
    private function defaultTerm(TokenCursor $tokenCursor): CodeNode
    {
        if ($tokenCursor->accept('current_timestamp') || $tokenCursor->accept('now', '(', ')')) {
            return new CodeValue('$defaults->now()');
        }

        $json = $this->jsonDefault($tokenCursor);

        return $json === ''
            ? $this->expressionReader->term($tokenCursor)
            : new CodeValue('$defaults->json(' . $json . ')');
    }

    /**
     * @param TokenCursor $tokenCursor After `DEFAULT`.
     *
     * @throws UnsupportedSql When a token it has just looked at is not there.
     *
     * @return string The JSON as a PHP literal when the default is a text literal cast to jsonb, which is read; empty
     *                otherwise, and nothing is read.
     */
    private function jsonDefault(TokenCursor $tokenCursor): string
    {
        $tokens = [$tokenCursor->peek(), $tokenCursor->peek(1), $tokenCursor->peek(2)];

        if (!$tokens[0] instanceof DumpToken || !$tokens[1] instanceof DumpToken || !$tokens[2] instanceof DumpToken) {
            return '';
        }

        if (
            $tokens[0]->dumpTokenKind !== DumpTokenKind::Text
            || !$tokens[1]->isSymbol('::')
            || !$tokens[2]->isWord('jsonb')
        ) {
            return '';
        }

        $tokenCursor->next();
        $tokenCursor->expect('::', 'jsonb');

        return $this->phpLiteral->string($tokens[0]->text);
    }

    /**
     * @param TokenCursor    $tokenCursor    After `GENERATED ALWAYS AS`.
     * @param ImportedColumn $importedColumn The column so far.
     *
     * @throws UnsupportedSql When the term is unsupported or the column is not `STORED`.
     *
     * @return ImportedColumn The column, generated.
     */
    private function generated(TokenCursor $tokenCursor, ImportedColumn $importedColumn): ImportedColumn
    {
        $group = $tokenCursor->group();
        $term = $this->expressionReader->term($group);
        $group->end();
        $tokenCursor->expect('stored');

        return $importedColumn->withGenerated($term);
    }
}
