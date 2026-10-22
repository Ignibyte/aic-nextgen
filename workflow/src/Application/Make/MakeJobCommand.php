<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\JobSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

/**
 * `bin/aic make:job <module> <Class> <name> [--dry-run] [--json]` (#122):
 * a job that is done at once. The manifest names it.
 */
final readonly class MakeJobCommand implements Command
{
    /**
     * @param MakeRun   $makeRun   The run.
     * @param MakeNamed $makeNamed The recipe it shares with make:command.
     * @param JobSource $jobSource Writes the job.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNamed $makeNamed = new MakeNamed(),
        private JobSource $jobSource = new JobSource(),
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
            new CommandUsage('make:job', 'make:job <module> <Class> <name> [--dry-run] [--json]', ['dry-run'], [], 3),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The job.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        return $this->makeNamed->recipe($parsedArguments, 'job', $this->jobSource->source(...));
    }
}
