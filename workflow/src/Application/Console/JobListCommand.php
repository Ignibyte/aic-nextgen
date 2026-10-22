<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\InspectAnswer;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Console\SurfaceView;
use Override;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `bin/aic job:list [--json]` (#109): every queue job with its module, its
 * class and the schedules that queue it.
 */
final readonly class JobListCommand implements Command
{
    /**
     * @param InspectRun $inspectRun The inspect commands' run.
     */
    public function __construct(
        private InspectRun $inspectRun,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @return CommandResponse The jobs.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->inspectRun->run($commandInput, new CommandUsage('job:list', 'job:list [--json]'), $this->answer(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param SurfaceView     $surfaceView     The surface.
     *
     * @return InspectAnswer The jobs, each with its schedules.
     */
    private function answer(ParsedArguments $parsedArguments, SurfaceView $surfaceView): InspectAnswer
    {
        $jobs = [];

        foreach ($surfaceView->jobs as $job) {
            $schedules = array_values(array_map(
                static fn (array $schedule): string => $schedule['name'],
                array_filter($surfaceView->schedules, static fn (array $schedule): bool => $schedule['job'] === $job['name']),
            ));
            $jobs[] = ['class' => $job['class'], 'module' => $job['module'], 'name' => $job['name'], 'schedules' => $schedules];
        }

        $rows = array_map(
            static fn (array $job): array => [$job['name'], $job['module'], $job['class'], implode(', ', $job['schedules'])],
            $jobs,
        );

        return new InspectAnswer(
            (new ConsoleOutput())->table(['NAME', 'MODULE', 'CLASS', 'SCHEDULES'], $rows, 'no jobs are declared.'),
            json_encode(['jobs' => $jobs], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $parsedArguments->positionals === [],
        );
    }
}
