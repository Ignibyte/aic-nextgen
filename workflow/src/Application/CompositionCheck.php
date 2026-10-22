<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\DeclaredEntry;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\SurfaceEntry;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_filter;
use function array_key_exists;
use function array_values;

/**
 * The composition check (#101, Droost's `composition_check` mapped to AIC's
 * five kinds): every entry point the spec declares is built as declared in
 * `build/surface.json` (kind, handler, module), and every entry point the run
 * added or changed against the base commit's surface is declared. It reads
 * the surface only once gate:24 has passed on the tree as it is, so the file
 * is known to match the build. A false declaration holds the phase whatever
 * else holds.
 */
final readonly class CompositionCheck implements PhaseCheck
{
    /**
     * What to do about a failure.
     */
    private const string REMEDY = "make the spec's ## Entry Points and the module manifests agree, then run bin/aic workflow:run again.";

    /**
     * @param Phase            $phase            The phase it runs at.
     * @param SurfaceSource    $surfaceSource    The surface now and at the base commit.
     * @param SpecFile         $specFile         The declared entry points.
     * @param EvidenceLog      $evidenceLog      gate:24's verdict.
     * @param EvidenceRecorder $evidenceRecorder Records this one.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private Phase $phase,
        private SurfaceSource $surfaceSource,
        private SpecFile $specFile,
        private EvidenceLog $evidenceLog,
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
     * @return CheckRow Passed when the surface is as declared; failed, naming each difference, otherwise.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $subjectHash = $this->runSubject->hash($runState);

        try {
            $gate = $this->evidenceLog->latest($runState->runStart->runId, 'gate:24');
            $failures = $gate instanceof CheckRecord && $gate->greenFor($subjectHash)
                ? $this->failures($runState)
                : ['not run: gate:24 has not passed on this tree, so build/surface.json is not known to match the build.'];
        } catch (WorkflowRefusal $workflowRefusal) {
            $failures = ['build/surface.json could not be read: ' . $workflowRefusal->getMessage()];
        }

        $findings = new Findings('composition', $failures, 'every entry point is built as declared.', $subjectHash);

        return $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), self::REMEDY);
    }

    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When a surface manifest cannot be read.
     *
     * @return list<string> Each difference.
     */
    private function failures(RunState $runState): array
    {
        $declared = $this->declared($runState->runStart->specPath);
        $built = $this->surfaceSource->entries(null);
        $baseCommit = $runState->runStart->baseCommit;
        $base = $baseCommit === null
            ? []
            : $this->surfaceSource->entries($baseCommit);
        $failures = [];

        foreach ($declared as $key => $declaredEntry) {
            $failures[] = $this->compare($declaredEntry, $built[$key] ?? null);
        }

        foreach ($built as $key => $surfaceEntry) {
            $failures[] = array_key_exists($key, $declared) ? '' : $this->undeclared($surfaceEntry, $base[$key] ?? null);
        }

        return array_values(array_filter($failures, static fn (string $failure): bool => $failure !== ''));
    }

    /**
     * @param string $specPath The spec's path.
     *
     * @return array<string, DeclaredEntry> The entry points it declares, by key.
     */
    private function declared(string $specPath): array
    {
        $declared = [];

        foreach ($this->specFile->entries($specPath) as $declaredEntry) {
            $declared[$declaredEntry->key()] = $declaredEntry;
        }

        return $declared;
    }

    /**
     * @param SurfaceEntry      $surfaceEntry An entry built and not declared.
     * @param SurfaceEntry|null $base         The same key at the base commit; null when it was not there.
     *
     * @return string The failure when the run added or changed it; empty when it is as the base commit built it.
     */
    private function undeclared(SurfaceEntry $surfaceEntry, ?SurfaceEntry $base): string
    {
        return $base instanceof SurfaceEntry && $base->line() === $surfaceEntry->line()
            ? ''
            : 'built and not declared: ' . $surfaceEntry->line() . '.';
    }

    /**
     * @param DeclaredEntry     $declaredEntry An entry the spec declares.
     * @param SurfaceEntry|null $surfaceEntry  The surface's entry by its key; null when there is none.
     *
     * @return string The difference; empty when it is built as declared.
     */
    private function compare(DeclaredEntry $declaredEntry, ?SurfaceEntry $surfaceEntry): string
    {
        $key = $declaredEntry->key();
        $declared = 'declared ' . $declaredEntry->kind;

        return match (true) {
            !$surfaceEntry instanceof SurfaceEntry => $declared . ' ' . $key . ', not built.',
            $surfaceEntry->kind !== $declaredEntry->kind => $declared . ', built ' . $surfaceEntry->kind . ': ' . $key . '.',
            $surfaceEntry->class !== $declaredEntry->class => $declared . ' ' . $key . ' as ' . $declaredEntry->class
                . ', built as ' . $surfaceEntry->class . '.',
            $surfaceEntry->module !== $declaredEntry->module => $declared . ' ' . $key . ' in ' . $declaredEntry->module
                . ', built in ' . $surfaceEntry->module . '.',
            default => '',
        };
    }
}
