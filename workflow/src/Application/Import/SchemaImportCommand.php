<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Domain\Console\CommandUsage;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic schema:import <dump.sql> --module=<m> [--tables=<a>,<b>] [--version=<YYYY_MM_DD_HHMMSS>]` (#318): a
 * `pg_dump --schema-only` becomes one baseline migration per table in AIC's Schema declarations, and the module's
 * manifest runs them. Anything Schema cannot declare is listed and nothing is written. It goes through the make
 * run, so the guard, the made ledger, `--dry-run` and `--json` behave as every make does.
 */
final readonly class SchemaImportCommand implements Command
{
    /**
     * @param MakeRun       $makeRun       The run.
     * @param SchemaImports $schemaImports What the dump makes.
     */
    public function __construct(
        private MakeRun $makeRun,
        private SchemaImports $schemaImports,
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse What it wrote, or why it wrote nothing.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage(
                'schema:import',
                'schema:import <dump.sql> --module=<module> [--tables=<a>,<b>] [--version=<YYYY_MM_DD_HHMMSS>] [--dry-run] [--json]',
                ['dry-run'],
                ['module', 'tables', 'version'],
                1,
            ),
            $this->schemaImports->recipe(...),
        );
    }
}
