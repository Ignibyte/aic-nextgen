<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\FrozenSpec;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function hash;
use function implode;

/**
 * Plan's exit (#101): the spec must read as a contract (criteria, a file
 * manifest, an entry-points section), and then it is frozen with the
 * project's gate names. A freeze that cannot be written holds plan, where
 * Droost let it pass.
 */
final readonly class ContractCheck implements PhaseCheck
{
    /**
     * What to do when the freeze was not written.
     */
    private const string WRITABLE = 'the operator makes var/workflow/ writable, then runs bin/aic workflow:run.';

    /**
     * @param SpecFile     $specFile     Reads the spec.
     * @param EvidenceLog  $evidenceLog  Keeps the freeze.
     * @param GateExecutor $gateExecutor The gate names.
     * @param UtcTime      $utcTime      The clock.
     */
    public function __construct(
        private SpecFile $specFile,
        private EvidenceLog $evidenceLog,
        private GateExecutor $gateExecutor,
        private UtcTime $utcTime,
    ) {
    }

    /**
     * @return Phase Plan.
     */
    #[Override]
    public function phase(): Phase
    {
        return Phase::Plan;
    }

    /**
     * @param RunState $runState The run, at plan.
     *
     * @return CheckRow Passed and frozen; blocked with what the spec lacks; failed when the freeze was not written.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $text = (string) $this->specFile->text($runState->runStart->specPath);
        $gates = $this->gateExecutor->names();
        $problems = $this->problems($text, $gates);

        return $problems === []
            ? $this->freeze($runState->runStart->runId, $text, $gates)
            : new CheckRow(
                'contract',
                CheckStatus::Blocked,
                implode(' ', $problems),
                'complete the spec, then run bin/aic workflow:run again.',
            );
    }

    /**
     * @param string       $runId The run.
     * @param string       $text  The spec, a contract.
     * @param list<string> $gates The project's gate names.
     *
     * @return CheckRow Passed when the freeze is written; failed when it is not.
     */
    private function freeze(string $runId, string $text, array $gates): CheckRow
    {
        try {
            $this->evidenceLog->freeze($runId, new FrozenSpec($text, hash('xxh128', $text), $this->utcTime->now(), $gates));
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CheckRow(
                'contract',
                CheckStatus::Held,
                'the freeze was not written: ' . $workflowRefusal->getMessage(),
                self::WRITABLE,
            );
        }

        return new CheckRow(
            'contract',
            CheckStatus::Passed,
            'the spec reads as a contract; frozen with ' . implode(', ', $gates) . '.',
            '',
        );
    }

    /**
     * @param string       $text  The spec; empty when it is not written.
     * @param list<string> $gates The project's gate names.
     *
     * @return list<string> What keeps plan from freezing.
     */
    private function problems(string $text, array $gates): array
    {
        $problems = $text === ''
            ? ['the spec is not written yet.']
            : $this->specFile->contract($text)->problems();

        return $gates === []
            ? [...$problems, 'vendor/bin/aic-gate --numbers names no gate: require aic/gates for development (#116).']
            : $problems;
    }
}
