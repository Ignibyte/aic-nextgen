<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\GateLogQuery;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use JsonException;
use Override;

use function in_array;

/** Retrieves one actual stored gate transcript without rerunning checks or opening a writable evidence connection. */
final readonly class WorkflowLogsCommand implements Command
{
    /**
     * @param RunStore         $runStore         Resolves the open or latest archived run through read operations.
     * @param GateLog          $gateLog          Reads one stored gate result.
     * @param GateLogArguments $gateLogArguments Validates selectors.
     * @param GateLogOutput    $gateLogOutput    Preserves stored output and identifies optional line windows.
     */
    public function __construct(
        private RunStore $runStore,
        private GateLog $gateLog,
        private GateLogArguments $gateLogArguments = new GateLogArguments(),
        private GateLogOutput $gateLogOutput = new GateLogOutput(),
    ) {
    }

    /**
     * @param CommandInput $commandInput A gate name, with optional exact selectors and an explicit line window.
     *
     * @throws InvalidArgumentException When the command response cannot represent its exit code.
     *
     * @return CommandResponse The stored output; exit 1 for unavailable evidence or 2 for malformed arguments.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            $gateLogQuery = $this->gateLogArguments->parse($commandInput);
            $runId = $this->runId($gateLogQuery);
            $gateLogEntry = $this->gateLog->find($gateLogQuery, $runId)
                ?? throw new WorkflowRefusal('no stored result matches run ' . $runId . ' and the supplied gate selectors.');

            return new CommandResult($this->gateLogOutput->lines($gateLogEntry, $gateLogQuery), 0);
        } catch (WorkflowRefusal $workflowRefusal) {
            $exit = $workflowRefusal->getCode() === 2
                ? 2
                : 1;

            return new CommandResult($this->gateLogOutput->refused(
                $workflowRefusal->getMessage(),
                $exit,
                in_array('--json', $commandInput->arguments(), true),
            ), $exit);
        } catch (JsonException) {
            $message = 'stored output is not valid UTF-8 for JSON; request plain text.';

            return new CommandResult($this->gateLogOutput->refused($message, 1, true), 1);
        }
    }

    /**
     * @param GateLogQuery $gateLogQuery The optional explicit run ID.
     *
     * @throws WorkflowRefusal When no run exists or its stored state is malformed.
     *
     * @return string The resolved run identifier.
     */
    private function runId(GateLogQuery $gateLogQuery): string
    {
        if ($gateLogQuery->runId !== '') {
            return $gateLogQuery->runId;
        }

        $runState = $this->runStore->load() ?? $this->runStore->lastArchived();

        if (!$runState instanceof RunState) {
            throw new WorkflowRefusal('no run exists; no gate result was recorded.');
        }

        return $runState->runStart->runId;
    }
}
