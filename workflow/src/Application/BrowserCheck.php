<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\BrowserItem;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_filter;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function mb_trim;
use function sprintf;

/**
 * The browser row (#174, after #101's ticks): a front-end change is looked at
 * with Playwright, and the engine runs each look itself. At check every
 * `## Browser Check` item names a test in `tests/browser/` that exists, is
 * ticked, and passes against the app's site through `vendor/bin/aic-gate
 * browser`. At complete each item says `keep` (the test stays, declared, and
 * gate:23 runs it from then on) or `drop` (the test is gone). Work with no
 * look and no `browser_testable: Yes` passes; plan's contract already refused
 * a front-end manifest without one.
 */
final readonly class BrowserCheck implements PhaseCheck
{
    /**
     * Why looks cannot run: no site is set.
     */
    private const string NO_SITE = 'the spec names browser looks, and config/workflow.php sets no site to run them against.';

    /**
     * What the operator does about it.
     */
    private const string SET_SITE = "the operator sets site in config/workflow.php to the app's URL, "
        . 'then runs bin/aic workflow:run from a terminal.';

    /**
     * What to do when a look falls short.
     */
    private const string REMEDY = 'write each look as a Playwright test in tests/browser/ (explore with npx playwright cli), run it with '
        . 'vendor/bin/aic-gate browser --base-url=<site> <test>, and tick its item with what you saw.';

    /**
     * What to do at complete.
     */
    private const string DECIDE = 'end each item, after saw:, with — keep (the test stays and is declared) or — drop (delete the test).';

    /**
     * How long all of a run's looks may take, in seconds.
     */
    private const int TIMEOUT = 600;

    /**
     * @param Phase            $phase            Check (the looks run) or complete (each is kept or dropped).
     * @param string           $site             The app's URL from config/workflow.php; empty when none is set.
     * @param string           $aicGate          `vendor/bin/aic-gate`, absolute.
     * @param ProcessRunner    $processRunner    Runs the looks.
     * @param ProjectFiles     $projectFiles     Whether each test is there.
     * @param SpecFile         $specFile         The browser items.
     * @param EvidenceRecorder $evidenceRecorder Records the verdict.
     * @param RunSubject       $runSubject       The tree's subject hash.
     * @param BrowserLooks     $browserLooks     Reads the items.
     */
    public function __construct(
        private Phase $phase,
        private string $site,
        private string $aicGate,
        private ProcessRunner $processRunner,
        private ProjectFiles $projectFiles,
        private SpecFile $specFile,
        private EvidenceRecorder $evidenceRecorder,
        private RunSubject $runSubject,
        private BrowserLooks $browserLooks = new BrowserLooks(),
    ) {
    }

    /**
     * @return Phase Check or complete.
     */
    #[Override]
    public function phase(): Phase
    {
        return $this->phase;
    }

    /**
     * @param RunState $runState The run.
     *
     * @return CheckRow The browser row.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $text = $this->specFile->text($runState->runStart->specPath);
        $specContract = $text === null
            ? null
            : $this->specFile->contract($text);
        $items = $specContract instanceof SpecContract
            ? $this->browserLooks->items($specContract)
            : [];

        if (!$specContract instanceof SpecContract || ($items === [] && !$specContract->browserTestable())) {
            return new CheckRow('browser', CheckStatus::Passed, 'the work names no browser look.', '');
        }

        return $this->judged($runState, $specContract, $items);
    }

    /**
     * @param RunState          $runState     The run.
     * @param SpecContract      $specContract The spec.
     * @param list<BrowserItem> $items        Its looks.
     *
     * @throws WorkflowRefusal When the tree's subject cannot be hashed.
     *
     * @return CheckRow The recorded row, or held for the operator when no site is set.
     */
    private function judged(RunState $runState, SpecContract $specContract, array $items): CheckRow
    {
        if ($this->phase === Phase::Check && $this->site === '') {
            return new CheckRow('browser', CheckStatus::Held, self::NO_SITE, self::SET_SITE);
        }

        try {
            $failures = $this->phase === Phase::Complete
                ? $this->undecided($specContract, $items)
                : $this->unlooked($items);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->evidenceRecorder->unrecorded('browser', $workflowRefusal);
        }

        return $this->row($runState, $failures, count($items));
    }

    /**
     * @param RunState     $runState The run.
     * @param list<string> $failures What fell short.
     * @param int          $looks    How many looks the spec names.
     *
     * @throws WorkflowRefusal When the tree's subject cannot be hashed.
     *
     * @return CheckRow The recorded row.
     */
    private function row(RunState $runState, array $failures, int $looks): CheckRow
    {
        $passed = $this->phase === Phase::Complete
            ? sprintf('each of %d look(s) is kept or dropped.', $looks)
            : sprintf('%d look(s) ran green against %s.', $looks, $this->site);
        $findings = new Findings('browser', $failures, $passed, $this->runSubject->hash($runState));

        $remedy = $this->phase === Phase::Complete
            ? self::DECIDE
            : self::REMEDY;

        return $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), $remedy);
    }

    /**
     * @param list<BrowserItem> $items The items.
     *
     * @throws WorkflowRefusal When a test cannot be read.
     *
     * @return list<string> Each item that names no test, is unticked or names a missing one; else a failed look.
     */
    private function unlooked(array $items): array
    {
        $failures = [];

        foreach ($items as $item) {
            $failures = [...$failures, ...$this->shortfalls($item)];
        }

        return $failures === []
            ? $this->ran(array_values(array_unique(array_map(static fn (BrowserItem $browserItem): string => $browserItem->spec, $items))))
            : $failures;
    }

    /**
     * @param BrowserItem $browserItem An item.
     *
     * @throws WorkflowRefusal When its test cannot be read.
     *
     * @return list<string> What it lacks at check.
     */
    private function shortfalls(BrowserItem $browserItem): array
    {
        $number = $browserItem->number;

        return match (true) {
            $browserItem->spec === '' => [sprintf('item %d names no tests/browser/<name>.spec.ts.', $number)],
            $this->projectFiles->read($browserItem->spec) === null => [sprintf('item %d: %s does not exist.', $number, $browserItem->spec)],
            !$browserItem->ticked => [sprintf('item %d is not ticked: run its look, then tick it with what you saw.', $number)],
            default => [],
        };
    }

    /**
     * @param list<string> $specs The looks' tests.
     *
     * @throws WorkflowRefusal When the run cannot be started.
     *
     * @return list<string> Nothing when every look passes; else the run's last lines.
     */
    private function ran(array $specs): array
    {
        $processResult = $this->processRunner->run([$this->aicGate, 'browser', '--base-url=' . $this->site, ...$specs], self::TIMEOUT);

        if ($processResult->exitCode === 0) {
            return [];
        }

        $lines = array_filter(explode("\n", $processResult->output), static fn (string $line): bool => mb_trim($line) !== '');
        $tail = implode(' | ', array_map(mb_trim(...), array_slice(array_values($lines), -8)));

        return [sprintf('the looks failed (exit %d): %s', $processResult->exitCode, $tail)];
    }

    /**
     * @param SpecContract      $specContract The spec.
     * @param list<BrowserItem> $items        The items.
     *
     * @throws WorkflowRefusal When a test cannot be read.
     *
     * @return list<string> Each item not decided, kept without its declared test, or dropped with its test still there.
     */
    private function undecided(SpecContract $specContract, array $items): array
    {
        $failures = [];

        foreach ($items as $item) {
            $failure = $this->decision($specContract, $item);

            if ($failure === '') {
                continue;
            }

            $failures[] = $failure;
        }

        return $failures;
    }

    /**
     * @param SpecContract $specContract The spec.
     * @param BrowserItem  $browserItem  An item.
     *
     * @throws WorkflowRefusal When its test cannot be read.
     *
     * @return string What its decision lacks; empty when it holds.
     */
    private function decision(SpecContract $specContract, BrowserItem $browserItem): string
    {
        $present = $browserItem->spec !== '' && $this->projectFiles->read($browserItem->spec) !== null;
        $number = $browserItem->number;

        return match ($browserItem->decision) {
            'keep' => $present && in_array($browserItem->spec, $specContract->files(), true)
                ? ''
                : sprintf('item %d keeps %s: keep the file and declare it in the File Manifest.', $number, $browserItem->spec),
            'drop' => $present
                ? sprintf('item %d drops %s: delete the file.', $number, $browserItem->spec)
                : '',
            default => sprintf('item %d is not decided: end it, after saw:, with — keep or — drop.', $number),
        };
    }
}
