<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_values;
use function implode;
use function in_array;
use function str_starts_with;

/**
 * `bin/aic workflow:probe [--scratch=<dir>] [--keep] [<scenario>…]` (#119):
 * replays the engine's planted cases in throwaway apps, one scenario at a
 * time, and exits 0 only when every expectation ran and held. With no names
 * it runs every numbered scenario; an `x-` one runs only by name. It needs
 * no `config/workflow.php`, and it refuses (exit 2) outside AIC's own
 * repository, whose fixture the scenarios plant into, while a gate run is
 * live, while another sweep runs, and on an unknown name.
 */
final readonly class WorkflowProbeCommand implements Command
{
    /**
     * What it takes.
     */
    private const string USAGE = 'workflow:probe [--scratch=<dir>] [--keep] [<scenario>...]';

    /**
     * @param ProbeSweep       $probeSweep       The sweep.
     * @param ProbeScenarios   $probeScenarios   The scenarios there are.
     * @param ProbeHost        $probeHost        Where it runs.
     * @param string           $scratch          Where the apps are made when `--scratch` names nowhere else.
     * @param ConsoleArguments $consoleArguments Reads the line.
     */
    public function __construct(
        private ProbeSweep $probeSweep,
        private ProbeScenarios $probeScenarios,
        private ProbeHost $probeHost,
        private string $scratch,
        private ConsoleArguments $consoleArguments = new ConsoleArguments(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @return CommandResponse The tally; exit 0 only when every expectation passed, 1 otherwise, 2 refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            $parsedArguments = $this->consoleArguments->parse($commandInput, $this->usage());
            $names = $this->names($parsedArguments->positionals);
            $this->host();
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:probe — ' . $workflowRefusal->getMessage()], 2);
        }

        $scratch = $parsedArguments->flag('scratch');

        try {
            $probeTally = $this->probeSweep->sweep(
                $scratch === '' ? $this->scratch : $scratch,
                $names,
                array_key_exists('keep', $parsedArguments->flags),
            );
        } finally {
            $this->probeHost->unlock();
        }

        return new CommandResult([], $probeTally->exit());
    }

    /**
     * @return CommandUsage What it takes: `--keep`, `--scratch=<dir>` and any number of scenario names.
     */
    private function usage(): CommandUsage
    {
        return new CommandUsage('workflow:probe', self::USAGE, ['keep'], ['scratch'], 0, 99);
    }

    /**
     * @param list<string> $named The scenarios the line names.
     *
     * @throws WorkflowRefusal When one is not a scenario.
     *
     * @return list<string> The scenarios to run: those named, or every numbered one.
     */
    private function names(array $named): array
    {
        $all = $this->probeScenarios->names();
        $unknown = array_diff($named, $all);

        if ($unknown !== []) {
            throw new WorkflowRefusal(
                'no scenario is named ' . implode(', ', $unknown) . '; the scenarios are ' . implode(', ', $all) . '.',
            );
        }

        return $named === []
            ? array_values(array_filter($all, static fn (string $name): bool => !str_starts_with($name, 'x-')))
            : array_values(array_filter($all, static fn (string $name): bool => in_array($name, $named, true)));
    }

    /**
     * @throws WorkflowRefusal When the sweep may not run here or now; the lock is taken when it may.
     */
    private function host(): void
    {
        if (!$this->probeHost->aic()) {
            throw new WorkflowRefusal("it runs only in AIC's own repository: its scenarios plant into the fixture app's modules.");
        }

        if ($this->probeHost->gateRunLive()) {
            throw new WorkflowRefusal('a gate run is live on this machine; the sweep runs its own and waits for none.');
        }

        if (!$this->probeHost->lock()) {
            throw new WorkflowRefusal('another sweep holds var/probe/lock; when none runs, remove the lock and start again.');
        }
    }
}
