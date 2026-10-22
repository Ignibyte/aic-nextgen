<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\PhaseReport;
use AIC\Workflow\Domain\RunState;

use function array_any;
use function in_array;

/**
 * The stall rule (#117): a holding row that repeats once with nothing the
 * agent could change moved (the same check, status and words, the same
 * progress key) becomes an operator hold, so the agent stops cleanly and the
 * operator decides. An `Asked` or `Held` row never stalls, and a previous
 * report with a `Held` row restarts the count after the operator resumed.
 */
final readonly class StalledRows
{
    /**
     * Who decides a stalled row, and how.
     */
    private const string REMEDY = 'The agent made no change that moves it. The operator decides: fix what it names (a browser tool for the '
        . 'session, the environment) and resume with bin/aic workflow:run from a terminal, or bin/aic workflow:reset.';

    /**
     * @param ProgressKey $progressKey What the agent could have changed.
     */
    public function __construct(
        private ProgressKey $progressKey,
    ) {
    }

    /**
     * @param RunState    $runState    The run, before the report is kept.
     * @param PhaseReport $phaseReport A fresh report of its current phase.
     *
     * @return PhaseReport The report stamped with its key, each stalled row held.
     */
    public function stamp(RunState $runState, PhaseReport $phaseReport): PhaseReport
    {
        $key = $this->progressKey->key($runState);
        $previous = $runState->reports[$phaseReport->phase->value] ?? null;
        $rows = [];

        foreach ($phaseReport->rows as $checkRow) {
            $rows[] = $previous instanceof PhaseReport && self::stalled($checkRow, $previous, $key)
                ? self::held($checkRow, $previous)
                : $checkRow;
        }

        return new PhaseReport($phaseReport->phase, $phaseReport->attempt, $rows, $key);
    }

    /**
     * @param CheckRow    $checkRow    A fresh row.
     * @param PhaseReport $phaseReport The phase's previous report.
     * @param string      $key         The fresh report's key.
     *
     * @return bool Whether the row holds as it held last time, on the same tree, with no operator hold between.
     */
    private static function stalled(CheckRow $checkRow, PhaseReport $phaseReport, string $key): bool
    {
        if (
            !$checkRow->checkStatus->holds()
            || in_array($checkRow->checkStatus, [CheckStatus::Asked, CheckStatus::Held], true)
            || $phaseReport->key !== $key
            || $phaseReport->held() !== []
        ) {
            return false;
        }

        return array_any(
            $phaseReport->blocked(),
            static fn (CheckRow $before): bool => $before->check === $checkRow->check
                && $before->checkStatus === $checkRow->checkStatus
                && $before->why === $checkRow->why,
        );
    }

    /**
     * @param CheckRow    $checkRow    A stalled row.
     * @param PhaseReport $phaseReport The report it repeats.
     *
     * @return CheckRow The row, held for the operator.
     */
    private static function held(CheckRow $checkRow, PhaseReport $phaseReport): CheckRow
    {
        $why = 'unchanged since attempt ' . $phaseReport->attempt . ': ' . $checkRow->why;

        return new CheckRow($checkRow->check, CheckStatus::Held, $why, self::REMEDY);
    }
}
