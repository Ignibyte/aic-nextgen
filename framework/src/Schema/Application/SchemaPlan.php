<?php

declare(strict_types=1);

namespace AIC\Schema\Application;

use AIC\Schema\Contracts\Schema;
use AIC\Schema\Contracts\TableBuilder;
use AIC\Schema\Domain\Identifier;
use Override;

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
     * @param PostgresDdl $postgresDdl The dialect.
     *
     * @return list<string> The statements, in order.
     */
    public function statements(PostgresDdl $postgresDdl): array
    {
        $statements = [];

        foreach ($this->drafts as $draft) {
            if (!($draft instanceof TableDraft)) {
                continue;
            }

            $statements[] = $postgresDdl->createTable($draft->table());
        }

        return $statements;
    }
}
