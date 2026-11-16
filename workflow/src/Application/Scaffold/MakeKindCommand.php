<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\MakeReport;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Scaffold\LoadedScaffold;
use AIC\Workflow\Domain\Scaffold\ScaffoldArg;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_map;
use function implode;
use function in_array;
use function str_starts_with;

/**
 * `bin/aic make <kind> <module> <Name> [--<arg>=…] [--dry-run] [--json]`
 * (#298): one of the app's scaffolds, loaded and checked, filled with the
 * line's values and written through the run every built-in make uses (the
 * planner's collisions, the guard's wall, the rollback, `code.made`).
 */
final readonly class MakeKindCommand implements Command
{
    /**
     * @param MakeRun        $makeRun        The run.
     * @param MakeReport     $makeReport     Answers a scaffold that does not load.
     * @param ScaffoldLoader $scaffoldLoader Loads and checks the scaffold.
     * @param ScaffoldRecipe $scaffoldRecipe Fills it.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeReport $makeReport,
        private ScaffoldLoader $scaffoldLoader,
        private ScaffoldRecipe $scaffoldRecipe,
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $arguments = $commandInput->arguments();
        $kind = $arguments[0] ?? '';

        try {
            $loadedScaffold = $this->scaffoldLoader->load(str_starts_with($kind, '-') ? '' : $kind);
        } catch (WorkflowRefusal $workflowRefusal) {
            $commandUsage = new CommandUsage('make', 'make <kind> <module> <Name> [--<arg>=…] [--dry-run] [--json]');

            return $this->makeReport->refused($commandUsage, $workflowRefusal, in_array('--json', $arguments, true));
        }

        return $this->makeRun->run(
            $commandInput,
            self::usage($loadedScaffold),
            fn (ParsedArguments $parsedArguments): MakeRecipe => $this->scaffoldRecipe->recipe($loadedScaffold, $parsedArguments),
        );
    }

    /**
     * @param LoadedScaffold $loadedScaffold The scaffold.
     *
     * @return CommandUsage What a make of it takes: the kind, the module, the name, and each argument as a flag.
     */
    public static function usage(LoadedScaffold $loadedScaffold): CommandUsage
    {
        $scaffoldManifest = $loadedScaffold->scaffoldManifest;
        $flags = array_map(
            static fn (ScaffoldArg $scaffoldArg): string => $scaffoldArg->default === null
                ? '--' . $scaffoldArg->name . '=<' . $scaffoldArg->argType->value . '>'
                : '[--' . $scaffoldArg->name . '=<' . $scaffoldArg->argType->value . '>]',
            $scaffoldManifest->args,
        );

        return new CommandUsage(
            'make',
            implode(' ', ['make', $scaffoldManifest->kind, '<module>', '<Name>', ...$flags, '[--dry-run] [--json]']),
            ['dry-run'],
            array_map(static fn (ScaffoldArg $scaffoldArg): string => $scaffoldArg->name, $scaffoldManifest->args),
            3,
        );
    }
}
