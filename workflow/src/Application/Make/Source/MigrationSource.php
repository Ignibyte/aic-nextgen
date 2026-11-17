<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A migration (`make:migration`): a table with an id and a creation time (#122), and the columns of an entity's fields
 * (`make:entity`, #123).
 */
final readonly class MigrationSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The migration.
     * @param string $table     Its table.
     * @param string $version   The version the ledger records.
     * @param string $columns   The field columns after the id, each on its own line from its `->`; empty for none.
     * @param string $made      The make that made it.
     * @param string $key       The `id` column's builder call: an identity, or a uuid key for make:entity --key=uuid (#307).
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(
        string $namespace,
        string $class,
        string $table,
        string $version,
        string $columns = '',
        string $made = 'make:migration (#122)',
        string $key = "->identity('id')",
    ): string {
        return $this->makeTemplates->render('migration', 'migration.php.tpl', [
            'class' => $class,
            'columns' => $columns,
            'key' => $key,
            'made' => $made,
            'namespace' => $namespace,
            'table' => $table,
            'version' => $version,
        ]);
    }
}
