<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\SeekerInspection;
use AIC\Workflow\Exception\WorkflowRefusal;

use function count;
use function trim;

/**
 * `workflow:seeker-report`'s work (#103, Droost's `recordSeeker`): the
 * ledger from standard input, refused at the first failure, in order: no
 * input, no run at code, a counted inspection already filed (one pass, #127),
 * the parse, a `Subject:` other than the tree, no passing gate verdict on the
 * tree, the dispatch. Then the inspection and its findings are stored and
 * `seeker.filed` is logged. It never advances the run.
 */
final readonly class SeekerFiling
{
    /**
     * @param SeekerRun          $seekerRun          The open run.
     * @param RunHost            $runHost            Standard input.
     * @param SeekerLedgerParser $seekerLedgerParser Reads the ledger.
     * @param SeekerTree         $seekerTree         The tree it read, and the gates on it.
     * @param SeekerVerifier     $seekerVerifier     The guard's record of the return.
     * @param SeekerLog          $seekerLog          Stores the inspection.
     * @param SeekerJournal      $seekerJournal      `seeker.filed`.
     * @param UtcTime            $utcTime            The clock.
     * @param SeekerDigest       $seekerDigest       The ledger's digest.
     */
    public function __construct(
        private SeekerRun $seekerRun,
        private RunHost $runHost,
        private SeekerLedgerParser $seekerLedgerParser,
        private SeekerTree $seekerTree,
        private SeekerVerifier $seekerVerifier,
        private SeekerLog $seekerLog,
        private SeekerJournal $seekerJournal,
        private UtcTime $utcTime,
        private SeekerDigest $seekerDigest = new SeekerDigest(),
    ) {
    }

    /**
     * @throws WorkflowRefusal At the first thing that keeps the ledger from being filed; nothing is recorded then.
     *
     * @return list<string> What was filed, then any notes.
     */
    public function file(): array
    {
        $text = $this->text();
        [$runState, $phase] = $this->seekerRun->open();
        $runId = $runState->runStart->runId;
        $this->once($runId);
        $seekerLedger = $this->seekerLedgerParser->parse($text);
        $this->seekerTree->check($runState, $seekerLedger);
        $digest = $this->seekerDigest->digest($text);
        $seekerInspection = new SeekerInspection(
            $this->seekerLog->nextRound($runId),
            $phase,
            $seekerLedger,
            $this->seekerVerifier->dispatch($runId, $seekerLedger, $digest),
            $digest,
            $this->utcTime->now(),
        );
        $this->seekerLog->file($runId, $seekerInspection);

        return [$this->summary($seekerInspection), ...$this->seekerJournal->filed($runState, $seekerInspection)];
    }

    /**
     * @throws WorkflowRefusal When standard input is a terminal or holds nothing.
     *
     * @return string What was piped in.
     */
    private function text(): string
    {
        $text = $this->runHost->standardInput()
            ?? throw new WorkflowRefusal('standard input is a terminal: pipe the seeker\'s "## Seeker Inspection" section in.');

        if (trim($text) === '') {
            throw new WorkflowRefusal('nothing was piped in: pipe the seeker\'s "## Seeker Inspection" section in.');
        }

        return $text;
    }

    /**
     * @param string $runId The run.
     *
     * @throws WorkflowRefusal When a counted inspection is already filed for it, or the store cannot be read.
     */
    private function once(string $runId): void
    {
        $seekerInspection = $this->seekerLog->latestCounted($runId);

        if ($seekerInspection instanceof SeekerInspection) {
            throw new WorkflowRefusal(
                'round ' . $seekerInspection->round . ' is counted already: a run has one seeker pass, so run bin/aic workflow:run.',
            );
        }
    }

    /**
     * @param SeekerInspection $seekerInspection The filed inspection.
     *
     * @return string What was filed.
     */
    private function summary(SeekerInspection $seekerInspection): string
    {
        $seekerLedger = $seekerInspection->seekerLedger;

        return 'seeker-report — round ' . $seekerInspection->round . ' at ' . $seekerInspection->phase->value . ': '
            . $seekerLedger->seekerInspector->value . ', ' . $seekerInspection->seekerDispatch->value . ', '
            . ($seekerInspection->counted() ? 'counted' : 'not counted') . '; ' . count($seekerLedger->findings) . ' finding(s), '
            . count($seekerLedger->holding()) . ' CRITICAL or HIGH, advisory. Run bin/aic workflow:run next.';
    }
}
