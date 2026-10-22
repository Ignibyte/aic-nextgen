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
use Closure;
use Override;

use function array_column;
use function array_map;
use function array_values;
use function in_array;
use function json_encode;
use function ksort;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `bin/aic command:list [--json]` (#109): every console command, sorted by
 * name, with its kind, where it comes from (a module, the framework, the
 * workflow) and whether it exists in production (`runtime`) or only with the
 * dev-only workflow installed (`dev`).
 */
final readonly class CommandListCommand implements Command
{
    /**
     * @param InspectRun                        $inspectRun The inspect commands' run.
     * @param Closure(): array<string, Command> $workflow   The workflow's commands, by name.
     */
    public function __construct(
        private InspectRun $inspectRun,
        private Closure $workflow,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @return CommandResponse The commands.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->inspectRun->run($commandInput, new CommandUsage('command:list', 'command:list [--json]'), $this->answer(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param SurfaceView     $surfaceView     The surface.
     *
     * @return InspectAnswer Every command, the surface's and the workflow's.
     */
    private function answer(ParsedArguments $parsedArguments, SurfaceView $surfaceView): InspectAnswer
    {
        $commands = [...self::surfaceRows($surfaceView), ...$this->workflowRows($surfaceView)];
        ksort($commands);
        $rows = array_map(
            static fn (array $command): array => [
                $command['name'],
                $command['kind'],
                $command['source'],
                $command['where'],
                $command['class'],
            ],
            array_values($commands),
        );

        return new InspectAnswer(
            (new ConsoleOutput())->table(['NAME', 'KIND', 'SOURCE', 'WHERE', 'CLASS'], $rows, 'no commands.'),
            json_encode(['commands' => array_values($commands)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $parsedArguments->positionals === [],
        );
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     *
     * @return array<string, array<string, string>> The workflow's commands the surface does not list, which exist only with the
     *                                              dev-only workflow installed.
     */
    private function workflowRows(SurfaceView $surfaceView): array
    {
        $surfaceNames = array_column($surfaceView->commands, 'name');
        $commands = [];

        foreach (($this->workflow)() as $name => $command) {
            if (in_array($name, $surfaceNames, true)) {
                continue;
            }

            $commands[$name] = ['class' => $command::class, 'kind' => 'Command', 'name' => $name, 'source' => 'workflow', 'where' => 'dev'];
        }

        return $commands;
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     *
     * @return array<string, array<string, string>> The modules' and the framework's commands, which exist in production.
     */
    private static function surfaceRows(SurfaceView $surfaceView): array
    {
        $commands = [];

        foreach ($surfaceView->commands as $command) {
            $commands[$command['name']] = [
                'class' => $command['class'],
                'kind' => $command['kind'],
                'name' => $command['name'],
                'source' => $command['module'] === 'framework' ? 'framework' : 'module:' . $command['module'],
                'where' => 'runtime',
            ];
        }

        return $commands;
    }
}
