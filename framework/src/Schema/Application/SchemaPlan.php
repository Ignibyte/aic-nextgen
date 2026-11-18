<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\Expressions;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableAlteration;
use AIC\Schema\Contracts\TableBuilder;
use AIC\Schema\Domain\GuardKind;
use AIC\Schema\Domain\Identifier;
use AIC\Schema\Domain\TableGuard;
use AIC\Schema\Domain\View;
use InvalidArgumentException;
use Override;

use function array_map;
use function array_merge;

/**
 * The Schema one migration describes its tables on: it keeps each table's
 * draft, in order, and compiles them to statements.
 */
final class SchemaPlan implements Schema
{
    /**
     * The TableParts, AlterationDrafts, Views and TableGuards, in order.
     *
     * @var list<object>
     */
    private array $drafts = [];

    /**
     * The extensions needed, in order.
     *
     * @var list<Identifier>
     */
    private array $extensions = [];

    /**
     * The column types its builders and migrations name.
     */
    private readonly ColumnTypes $columnTypes;

    /**
     * The terms and predicates its migrations declare with (#310).
     */
    private readonly Expressions $expressions;

    /**
     * A plan with nothing described yet.
     */
    public function __construct()
    {
        $this->columnTypes = new ColumnTypeFactory();
        $this->expressions = new PostgresExpressions();
    }

    /**
     * @return Expressions The typed terms and predicates checks, partial indexes and generated columns take (#310).
     */
    #[Override]
    public function expressions(): Expressions
    {
        return $this->expressions;
    }

    /**
     * @param string $table A table this migration or an earlier one created.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     */
    #[Override]
    public function appendOnly(string $table): void
    {
        $this->drafts[] = new TableGuard(GuardKind::AppendOnly, new Identifier($table), []);
    }

    /**
     * @param string       $table   A table this migration or an earlier one created.
     * @param list<string> $columns Its nullable columns that, once set, stay as they are.
     *
     * @throws InvalidArgumentException When a name is not an identifier or there are no columns.
     */
    #[Override]
    public function finalOnce(string $table, array $columns): void
    {
        $identifiers = array_map(static fn (string $column): Identifier => new Identifier($column), $columns);
        $this->drafts[] = new TableGuard(GuardKind::FinalOnce, new Identifier($table), $identifiers);
    }

    /**
     * @return ColumnTypes Every column type a migration can name (#86).
     */
    #[Override]
    public function types(): ColumnTypes
    {
        return $this->columnTypes;
    }

    /**
     * @param string $table The new table's name.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return TableBuilder Its columns, added in order.
     */
    #[Override]
    public function create(string $table): TableBuilder
    {
        $tableParts = new TableParts(new Identifier($table));
        $this->drafts[] = $tableParts;

        return $tableParts->builder($this->columnTypes);
    }

    /**
     * @param string $table A table an earlier migration created.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     *
     * @return TableAlteration Its columns to add or change, and its constraints and indexes to add.
     */
    #[Override]
    public function alter(string $table): TableAlteration
    {
        $alterationDraft = new AlterationDraft(new Identifier($table));
        $this->drafts[] = $alterationDraft;

        return $alterationDraft;
    }

    /**
     * @param string $name A Postgres extension the migration needs.
     *
     * @throws InvalidArgumentException When the name is not an identifier.
     */
    #[Override]
    public function extension(string $name): void
    {
        $this->extensions[] = new Identifier($name);
    }

    /**
     * @param string $name   The view's name, an identifier.
     * @param string $select The SELECT it shows.
     *
     * @throws InvalidArgumentException When the name is not an identifier, or the SELECT is empty.
     */
    #[Override]
    public function view(string $name, string $select): void
    {
        $this->drafts[] = new View(new Identifier($name), $select);
    }

    /**
     * @param PostgresDdl $postgresDdl The dialect.
     *
     * @throws InvalidArgumentException When a table names a column it lacks, an alteration adds nothing, or an index name is too long.
     *
     * @return list<string> The statements, in order: the extensions first, then each table with its indexes, each
     *                      alteration, each view and each guard.
     */
    public function statements(PostgresDdl $postgresDdl): array
    {
        $statements = array_map($postgresDdl->createExtension(...), $this->extensions);

        foreach ($this->drafts as $draft) {
            $statements = array_merge($statements, self::drafted($draft, $postgresDdl));
        }

        return $statements;
    }

    /**
     * @param object      $draft       A TableParts, an AlterationDraft, a View or a TableGuard.
     * @param PostgresDdl $postgresDdl The dialect.
     *
     * @throws InvalidArgumentException When a table names a column it lacks, an alteration adds nothing, or an index name is too long.
     *
     * @return list<string> Its statements.
     */
    private static function drafted(object $draft, PostgresDdl $postgresDdl): array
    {
        if ($draft instanceof AlterationDraft) {
            return $postgresDdl->alterTable($draft->alteration());
        }

        if ($draft instanceof View) {
            return [$postgresDdl->createView($draft)];
        }

        if ($draft instanceof TableGuard) {
            return $postgresDdl->createGuard($draft);
        }

        if (!$draft instanceof TableParts) {
            return [];
        }

        $table = $draft->table();

        return array_merge([$postgresDdl->createTable($table)], $postgresDdl->createIndexes($table));
    }
}
