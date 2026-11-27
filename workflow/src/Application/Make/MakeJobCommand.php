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
use InvalidArgumentException;
use Override;

use function array_key_exists;

/**
 * `bin/aic make:job <module> <Class> <name> [--dry-run] [--json]` (#122):
 * a job that is done at once. The manifest names it. With `--worker` the
 * first word names an interpreted worker and there is no name (#336).
 */
final readonly class MakeJobCommand implements Command
{
    /**
     * @param MakeRun         $makeRun         The run.
     * @param WorkerJobRecipe $workerJobRecipe The recipe of a worker's job.
     * @param MakeNamed       $makeNamed       The recipe it shares with make:command.
     * @param JobSource       $jobSource       Writes the job.
     */
    public function __construct(
        private MakeRun $makeRun,
        private WorkerJobRecipe $workerJobRecipe,
        private MakeNamed $makeNamed = new MakeNamed(),
        private JobSource $jobSource = new JobSource(),
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
            new CommandUsage(
                'make:job',
                'make:job <module> <Class> <name> | <worker> <Class> --worker [--dry-run] [--json]',
                ['dry-run', 'worker'],
                [],
                2,
                1,
            ),
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
        return array_key_exists('worker', $parsedArguments->flags)
            ? $this->workerJobRecipe->recipe($parsedArguments)
            : $this->makeNamed->recipe($parsedArguments, 'job', $this->jobSource->source(...));
    }
}
