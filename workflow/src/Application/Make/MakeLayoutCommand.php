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
 * `bin/aic make:layout <name> [--dry-run] [--json]` (#157): a named page layout in the theme's `layouts/`, its page
 * data with the content region only and its `page--<name>.phtml`. It edits no manifest; the next build adds its
 * `ThemeLayoutName` case, and `make:page … --layout=<name>` names it. Its document is the theme's `html.phtml`.
 */
final readonly class MakeLayoutCommand implements Command
{
    /**
     * @param MakeRun     $makeRun     The run.
     * @param MakeLayouts $makeLayouts Names and writes the layout.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeLayouts $makeLayouts,
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
            new CommandUsage('make:layout', 'make:layout <name> [--dry-run] [--json]', ['dry-run'], [], 1),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When the name breaks a rule, or the app names no theme.
     *
     * @return MakeRecipe The layout's two files.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $name = $this->makeLayouts->name($parsedArguments->positional(0));

        return new MakeRecipe('', $name, $this->makeLayouts->files($name), null);
    }
}
