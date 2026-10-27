<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\ColumnTypes;
use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableAlteration;
use AIC\Schema\Contracts\TableBuilder;
use AIC\Schema\Domain\Identifier;
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
     * The TableParts, AlterationDrafts and Views, in order.
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
     * A plan with nothing described yet.
     */
    public function __construct()
    {
        $this->columnTypes = new ColumnTypeFactory();
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
        $constraintDraft = new ConstraintDraft();
        $tableParts = new TableParts(new Identifier($table), $constraintDraft);
        $this->drafts[] = $tableParts;

        return new TableDraft($tableParts, $constraintDraft, $this->columnTypes);
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
        $alterationDraft = new AlterationDraft(new Identifier($table), new ColumnChanges(), new ConstraintDraft());
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
     *                      alteration and each view.
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
     * @param object      $draft       A TableParts, an AlterationDraft or a View.
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

        if (!$draft instanceof TableParts) {
            return [];
        }

        $table = $draft->table();

        return array_merge([$postgresDdl->createTable($table)], $postgresDdl->createIndexes($table));
    }
}
