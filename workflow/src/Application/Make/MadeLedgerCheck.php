<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\EvidenceRecorder;
use AIC\Workflow\Application\PhaseCheck;
use AIC\Workflow\Application\RunHost;
use AIC\Workflow\Application\RunSubject;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Make\MadeRow;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_filter;
use function array_map;
use function array_search;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function implode;

/**
 * The made ledger's row (#302), `made` at code, check and complete: the
 * ledger only grows, so it begins with every line it had at the run's base;
 * and the lines after those are exactly the rows this run's `code.made`
 * events give, so a row no make wrote, or one a make wrote and someone
 * removed, holds the run.
 */
final readonly class MadeLedgerCheck implements PhaseCheck
{
    /**
     * What to do about a ledger that is not the engine's.
     */
    private const string REMEDY = 'restore ' . MadeLedger::PATH . ' to what the makes wrote: git checkout it from the base and '
        . 'make the files again with bin/aic make, or take them out.';

    /**
     * @param Phase            $phase            The phase it runs at.
     * @param MadeLedger       $madeLedger       The ledger and the run's makes.
     * @param RunHost          $runHost          The ledger at the base.
     * @param EvidenceRecorder $evidenceRecorder Records the verdict.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private Phase $phase,
        private MadeLedger $madeLedger,
        private RunHost $runHost,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
    ) {
    }

    /**
     * @return Phase The phase it runs at.
     */
    #[Override]
    public function phase(): Phase
    {
        return $this->phase;
    }

    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When the ledger or the event log cannot be read, or the verdict recorded.
     *
     * @return CheckRow Passed when the ledger is its base and this run's makes; failed, naming each line that is not.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $base = $this->base($runState);
        $now = $this->madeLedger->lines();
        $made = array_map(static fn (MadeRow $madeRow): string => $madeRow->line(), $this->madeLedger->made($runState->runStart->runId));
        $added = array_slice($now, count($base));
        $failures = array_slice($now, 0, count($base)) === $base
            ? []
            : ['the ledger does not begin with the ' . count($base) . ' line(s) it had at the base: it only grows.'];
        $failures = [
            ...$failures,
            ...self::named('line(s) no make of this run wrote', self::minus($added, $made)),
            ...self::named("line(s) this run's makes wrote are gone", self::minus($made, $added)),
        ];
        $passed = "the ledger is its base and this run's " . count($made) . ' made file(s).';
        $findings = new Findings('made', $failures, $passed, $this->runSubject->hash($runState));

        return $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), self::REMEDY);
    }

    /**
     * @param RunState $runState The run.
     *
     * @return list<string> The ledger's lines at the run's base; empty when it had none or the run has no base.
     */
    private function base(RunState $runState): array
    {
        $baseCommit = $runState->runStart->baseCommit;
        $text = $baseCommit === null
            ? null
            : $this->runHost->fileAt($baseCommit, MadeLedger::PATH);

        return array_values(array_filter(explode("\n", $text ?? ''), static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param string       $what  What the lines are.
     * @param list<string> $lines The lines.
     *
     * @return list<string> One failure naming them; empty when there are none.
     */
    private static function named(string $what, array $lines): array
    {
        return $lines === []
            ? []
            : [count($lines) . ' ' . $what . ': ' . implode(', ', $lines) . '.'];
    }

    /**
     * @param list<string> $lines  Lines.
     * @param list<string> $others Lines to take away, each once.
     *
     * @return list<string> The lines left, in order.
     */
    private static function minus(array $lines, array $others): array
    {
        $left = [];

        foreach ($lines as $line) {
            $found = array_search($line, $others, true);

            if ($found === false) {
                $left[] = $line;

                continue;
            }

            unset($others[$found]);
        }

        return $left;
    }
}
