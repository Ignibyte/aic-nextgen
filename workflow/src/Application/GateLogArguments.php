<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\CommandInput;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\GateLogQuery;
use AIC\Workflow\Domain\LogLineRange;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_key_exists;
use function array_search;
use function array_slice;
use function explode;
use function filter_var;
use function is_int;
use function preg_match;
use function str_starts_with;

use const FILTER_VALIDATE_INT;

/** Validates exact gate selectors and explicit line windows without accepting paths or SQL. */
final readonly class GateLogArguments
{
    /** @param ConsoleArguments $consoleArguments Validates the command's flags and argument count. */
    public function __construct(private ConsoleArguments $consoleArguments = new ConsoleArguments())
    {
    }

    /**
     * @param CommandInput $commandInput The supplied arguments.
     *
     * @throws WorkflowRefusal With exit code 2 when a selector is malformed or repeated.
     *
     * @return GateLogQuery The validated selectors.
     */
    public function parse(CommandInput $commandInput): GateLogQuery
    {
        $commandUsage = new CommandUsage('workflow:logs', 'workflow:logs gate[:N] [--run=run-ID] [--phase=code] [--attempt=N] '
            . '[--from-line=N] [--lines=N] [--json]', valued: ['run', 'phase', 'attempt', 'from-line', 'lines'], required: 1);
        $parsedArguments = $this->consoleArguments->parse($commandInput, $commandUsage);
        $this->unique($commandInput);

        return new GateLogQuery(
            $this->gate($parsedArguments->positional(0)),
            $this->runId($parsedArguments->flag('run')),
            $this->phase($parsedArguments->flag('phase')),
            $this->positive($parsedArguments->flag('attempt')),
            $this->range($parsedArguments),
            array_key_exists('json', $parsedArguments->flags),
        );
    }

    /**
     * @param string $gate The requested gate.
     *
     * @throws WorkflowRefusal When it is not an aggregate or numbered gate.
     *
     * @return string The exact gate selector.
     */
    private function gate(string $gate): string
    {
        if (preg_match('/^gate(?::(?:0|[1-9][0-9]*))?$/D', $gate) !== 1) {
            throw new WorkflowRefusal('workflow:logs requires gate or gate:N, such as gate:8.', 2);
        }

        return $gate;
    }

    /**
     * @param string $runId The optional run ID.
     *
     * @throws WorkflowRefusal When it is a path or malformed run identifier.
     *
     * @return string The ID, or empty for the open or latest archived run.
     */
    private function runId(string $runId): string
    {
        if ($runId !== '' && preg_match('/^run-[a-zA-Z0-9_-]+$/D', $runId) !== 1) {
            throw new WorkflowRefusal('--run requires a run-ID identifier, never a database or file path.', 2);
        }

        return $runId;
    }

    /**
     * @param string $phase The optional phase.
     *
     * @throws WorkflowRefusal When it is not a workflow phase.
     *
     * @return Phase|null The phase, or null for the latest matching record.
     */
    private function phase(string $phase): ?Phase
    {
        return match ($phase) {
            '' => null,
            'plan' => Phase::Plan,
            'code' => Phase::Code,
            'check' => Phase::Check,
            'complete' => Phase::Complete,
            default => throw new WorkflowRefusal('--phase requires plan, code, check or complete.', 2),
        };
    }

    /**
     * @param string $value An optional attempt or line number.
     *
     * @throws WorkflowRefusal When it is not a positive integer this host can represent.
     *
     * @return int|null The integer, or null when absent.
     */
    private function positive(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $number = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($number) || $number < 1 || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new WorkflowRefusal('--attempt, --from-line and --lines require positive integers.', 2);
        }

        return $number;
    }

    /**
     * @param ParsedArguments $parsedArguments The parsed flags.
     *
     * @throws WorkflowRefusal When a requested line bound is invalid.
     *
     * @return LogLineRange|null The explicit window, or null for full output.
     */
    private function range(ParsedArguments $parsedArguments): ?LogLineRange
    {
        $first = $this->positive($parsedArguments->flag('from-line'));
        $count = $this->positive($parsedArguments->flag('lines'));

        return $first === null && $count === null
            ? null
            : new LogLineRange($first ?? 1, $count);
    }

    /**
     * @param CommandInput $commandInput The original arguments, before repeated flags could overwrite one another.
     *
     * @throws WorkflowRefusal When a flag appears twice.
     */
    private function unique(CommandInput $commandInput): void
    {
        $seen = [];

        foreach ($this->beforeEnd($commandInput) as $word) {
            if (!str_starts_with($word, '--')) {
                continue;
            }

            $name = explode('=', $word, 2)[0];

            if (array_key_exists($name, $seen)) {
                throw new WorkflowRefusal($name . ' was given more than once.', 2);
            }

            $seen[$name] = true;
        }
    }

    /**
     * @param CommandInput $commandInput The supplied command arguments.
     *
     * @return list<string> Arguments before the explicit end of options.
     */
    private function beforeEnd(CommandInput $commandInput): array
    {
        $words = $commandInput->arguments();
        $end = array_search('--', $words, true);

        return $end === false
            ? $words
            : array_slice($words, 0, $end);
    }
}
