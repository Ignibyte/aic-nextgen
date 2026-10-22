<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_key_exists;

/**
 * `bin/aic make:component <module> <Class> [--export] [--dry-run] [--json]`
 * (#122): the component, one promoted `title`, and its `*.html.php`; with
 * `--export`, the manifest exports it. A page gives it its caller
 * (`make:page … --component=<Class>`).
 */
final readonly class MakeComponentCommand implements Command
{
    /**
     * @param MakeRun        $makeRun        The run.
     * @param MakeComponents $makeComponents Writes the component and its template.
     * @param MakeNames      $makeNames      The rules.
     * @param MakeCalls      $makeCalls      The export call.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeComponents $makeComponents = new MakeComponents(),
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage(
                'make:component',
                'make:component <module> <Class> [--export] [--dry-run] [--json]',
                ['export', 'dry-run'],
                [],
                2,
            ),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The component and its template.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('', $parsedArguments);
        $call = array_key_exists('export', $parsedArguments->flags)
            ? $this->makeCalls->classOnly('export', 'Component', $class, $namespace)
            : null;

        return new MakeRecipe($module, $class, $this->makeComponents->files($module, $namespace, $class, 'make:component (#122)'), $call);
    }
}
