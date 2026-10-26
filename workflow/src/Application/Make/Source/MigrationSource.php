<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A migration (`make:migration`): a table with an id and a creation time (#122), and the columns of an entity's fields
 * (`make:entity`, #123).
 */
final readonly class MigrationSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Migration;

        use AIC\Schema\Contracts\Migration;
        use AIC\Schema\Contracts\Schema;
        use Override;

        /**
         * The `{table}` table, made by bin/aic {made}.
         */
        final readonly class {class} implements Migration
        {
            /**
             * @return string The version the ledger records it under.
             */
            #[Override]
            public function version(): string
            {
                return '{version}';
            }

            /**
             * @param Schema $schema Where the migration describes its tables.
             */
            #[Override]
            public function describe(Schema $schema): void
            {
                $schema->create('{table}')
                    ->identity('id'){columns}
                    ->timestampTz('created_at')
                    ->primary(['id']);
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The migration.
     * @param string $table     Its table.
     * @param string $version   The version the ledger records.
     * @param string $columns   The field columns after the id, each on its own line from its `->`; empty for none.
     * @param string $made      The make that made it.
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
    ): string {
        return strtr(self::TEMPLATE, [
            '{class}' => $class,
            '{columns}' => $columns,
            '{made}' => $made,
            '{namespace}' => $namespace,
            '{table}' => $table,
            '{version}' => $version,
        ]) . "\n";
    }
}
