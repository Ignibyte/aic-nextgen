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
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:component <Class> [--dry-run] [--json]` (#122, #153): the
 * component, one promoted `title`, and its template, together in the theme's
 * `components/<Class>/`. It edits no manifest: any module may build it. A page
 * gives it its caller (`make:page … --component=<Class>`).
 */
final readonly class MakeComponentCommand implements Command
{
    /**
     * @param MakeRun        $makeRun        The run.
     * @param MakeComponents $makeComponents Writes the component and its template in the theme.
     * @param MakeNames      $makeNames      The rules.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeComponents $makeComponents,
        private MakeNames $makeNames = new MakeNames(),
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
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage('make:component', 'make:component <Class> [--dry-run] [--json]', ['dry-run'], [], 1),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When the name breaks a rule, or the app names no theme.
     *
     * @return MakeRecipe The component and its template.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $class = $this->makeNames->class($parsedArguments->positional(0), '');

        return new MakeRecipe(
            '',
            $class,
            $this->makeComponents->files($class, 'make:component (#122)'),
            null,
        );
    }
}
