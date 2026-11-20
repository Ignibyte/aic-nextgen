<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\MakeCalls;
use AIC\Workflow\Application\Make\MakeNames;
use AIC\Workflow\Domain\Import\ImportedTable;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\ManifestCall;
use AIC\Workflow\Exception\UnsupportedSql;
use AIC\Workflow\Exception\WorkflowRefusal;

use function implode;
use function sprintf;
use function str_replace;
use function substr;
use function ucwords;

/**
 * The files a schema import writes (#318): each chosen table's migration, `Create<Table>Table`, versioned in order
 * from the first stamp, and the manifest calls that run them.
 */
final readonly class ImportFiles
{
    /**
     * @param MakeNames       $makeNames       The rules.
     * @param MakeCalls       $makeCalls       The manifest calls.
     * @param MigrationWriter $migrationWriter Writes each migration.
     */
    public function __construct(
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private MigrationWriter $migrationWriter = new MigrationWriter(),
    ) {
    }

    /**
     * @param list<ImportedTable> $tables    The tables, in order.
     * @param string              $module    The module.
     * @param string              $namespace Its namespace.
     * @param string              $stamp     The first migration's stamp.
     *
     * @throws WorkflowRefusal When a table's name breaks the rules or a migration cannot be written gate-clean.
     *
     * @return array{list<MadeFile>, ManifestCall|null} The migrations, and the calls that run them.
     */
    public function files(array $tables, string $module, string $namespace, string $stamp): array
    {
        $files = [];
        $findings = [];
        $call = null;

        foreach ($tables as $index => $importedTable) {
            $table = $this->makeNames->word($importedTable->name, 'table');
            $class = 'Create' . str_replace('_', '', ucwords($table, '_')) . 'Table';
            $call = $this->call($call, $this->makeCalls->classOnly('migration', 'Migration', $class, $namespace));

            try {
                $version = $this->version($stamp, $index) . '_create_' . $table . '_table';
                $source = $this->migrationWriter->source($importedTable, $namespace, $class, $version);
                $files[] = new MadeFile('modules/' . $module . '/src/Migration/' . $class . '.php', $source);
            } catch (UnsupportedSql $unsupportedSql) {
                $findings[] = $table . ': ' . $unsupportedSql->getMessage();
            }
        }

        return $findings === []
            ? [$files, $call]
            : throw new WorkflowRefusal(
                "an imported table's migration cannot be written gate-clean, so nothing was written:\n- " . implode("\n- ", $findings),
            );
    }

    /**
     * @param string $stamp The first migration's stamp.
     * @param int    $index Which migration.
     *
     * @throws WorkflowRefusal When the stamp's last six digits run out.
     *
     * @return string Its stamp: the first's, its last six digits counted up.
     */
    private function version(string $stamp, int $index): string
    {
        $number = (int) substr($stamp, 11) + $index;

        return $number <= 999_999 ? substr($stamp, 0, 11) . sprintf('%06d', $number) : throw new WorkflowRefusal(
            '--version ' . $stamp . ' leaves no room for ' . ($index + 1) . ' migrations; give an earlier stamp.',
            ConsoleArguments::USAGE,
        );
    }

    /**
     * @param ManifestCall|null $first  The calls so far.
     * @param ManifestCall      $second The next.
     *
     * @return ManifestCall Both.
     */
    private function call(?ManifestCall $first, ManifestCall $second): ManifestCall
    {
        return $first === null
            ? $second
            : $this->makeCalls->both($first, $second);
    }
}
