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

use function array_map;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `bin/aic schedule:list [--json]` (#109): every schedule with its cadence,
 * the job it queues and the module that declares it.
 */
final readonly class ScheduleListCommand implements Command
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
     * @return CommandResponse The schedules.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->inspectRun->run($commandInput, new CommandUsage('schedule:list', 'schedule:list [--json]'), $this->answer(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param SurfaceView     $surfaceView     The surface.
     *
     * @return InspectAnswer The schedules.
     */
    private function answer(ParsedArguments $parsedArguments, SurfaceView $surfaceView): InspectAnswer
    {
        $rows = array_map(
            static fn (array $schedule): array => [$schedule['name'], $schedule['cadence'], $schedule['job'], $schedule['module']],
            $surfaceView->schedules,
        );

        return new InspectAnswer(
            (new ConsoleOutput())->table(['NAME', 'CADENCE', 'JOB', 'MODULE'], $rows, 'no schedules are declared.'),
            json_encode(['schedules' => $surfaceView->schedules], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $parsedArguments->positionals === [],
        );
    }
}
