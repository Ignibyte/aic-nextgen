<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use AIC\Schema\Domain\Identifier;
use InvalidArgumentException;
use Override;

use function array_map;

/**
 * The Schema one migration describes its tables on: it keeps each table's
 * draft, in order, and compiles them to statements.
 */
final class SchemaPlan implements Schema
{
    /**
     * The TableDrafts, in order.
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
     * @param string $table The new table's name.
     *
     * @return TableBuilder Its columns, added in order.
     */
    #[Override]
    public function create(string $table): TableBuilder
    {
        $tableDraft = new TableDraft(new Identifier($table));
        $this->drafts[] = $tableDraft;

        return $tableDraft;
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
     * @param PostgresDdl $postgresDdl The dialect.
     *
     * @throws InvalidArgumentException When a table names a column it lacks, or an index name is too long.
     *
     * @return list<string> The statements, in order: the extensions first, then each table followed by its indexes.
     */
    public function statements(PostgresDdl $postgresDdl): array
    {
        $statements = array_map($postgresDdl->createExtension(...), $this->extensions);

        foreach ($this->drafts as $draft) {
            if (!($draft instanceof TableDraft)) {
                continue;
            }

            $table = $draft->table();
            $statements[] = $postgresDdl->createTable($table);

            foreach ($postgresDdl->createIndexes($table) as $index) {
                $statements[] = $index;
            }
        }

        return $statements;
    }
}
