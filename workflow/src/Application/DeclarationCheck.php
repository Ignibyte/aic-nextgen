<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_diff;
use function array_slice;
use function count;
use function implode;
use function in_array;
use function str_ends_with;
use function str_starts_with;

/**
 * The declaration audit (#101, Droost's `DeclarationAudit` with exact paths
 * only): every path the run changed must be a row of the spec's file
 * manifest, or it holds the phase as the agent's. Exempt are the build's
 * outputs, which gate:24 and the composition check hold, and the engine's
 * own writes. A declared path left untouched is recorded.
 */
final readonly class DeclarationCheck implements PhaseCheck
{
    /**
     * What `composer run build` writes, exactly (#167: its two manifests and its two folders under `build/`, and the
     * wiki), and the engine's state; any other file under `build/` is declared like a source.
     */
    private const array EXEMPT = [
        'build/assets.json',
        'build/surface.json',
        'build/generated/',
        'build/assets/',
        'var/workflow/',
        'docs/wiki/',
    ];

    /**
     * The undeclared paths a summary names at most (Droost's `SUMMARY_PATHS`).
     */
    private const int NAMED = 20;

    /**
     * What to do about an undeclared path.
     */
    private const string REMEDY = "declare each path as a row of the spec's ### File Manifest, or take the change out.";

    /**
     * @param Phase            $phase            The phase it runs at.
     * @param RunHost          $runHost          What changed.
     * @param SpecFile         $specFile         The manifest.
     * @param EvidenceRecorder $evidenceRecorder Records the verdict.
     * @param RunSubject       $runSubject       The subject hash, and the engine's own writes.
     */
    public function __construct(
        private Phase $phase,
        private RunHost $runHost,
        private SpecFile $specFile,
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
     * @throws WorkflowRefusal When a called boundary reports its documented failure.
     *
     * @return CheckRow Passed when every change is declared; failed, naming the rest, otherwise.
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $text = $this->specFile->text($runState->runStart->specPath);
        $declared = $text === null
            ? []
            : $this->specFile->contract($text)->files();
        $changed = $this->runHost->changedFiles($runState->runStart->baseCommit);
        $undeclared = $this->undeclared($changed, $declared);
        $untouched = array_diff($declared, $changed);
        $named = count($undeclared) . ' changed path(s) not in the file manifest: '
            . implode(', ', array_slice($undeclared, 0, self::NAMED)) . '.';
        $passed = 'every changed path is declared.'
            . ($untouched === [] ? '' : ' Declared and untouched: ' . implode(', ', $untouched) . '.');
        $findings = new Findings('declaration', $undeclared === [] ? [] : [$named], $passed, $this->runSubject->hash($runState));

        return $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), self::REMEDY);
    }

    /**
     * @param list<string> $changed  The changed paths.
     * @param list<string> $declared The declared paths.
     *
     * @return list<string> The changed paths neither declared nor exempt.
     */
    private function undeclared(array $changed, array $declared): array
    {
        $undeclared = [];

        foreach ($changed as $path) {
            if (in_array($path, $declared, true) || $this->exempt($path)) {
                continue;
            }

            $undeclared[] = $path;
        }

        return $undeclared;
    }

    /**
     * @param string $path A changed path.
     *
     * @return bool Whether the audit leaves it out.
     */
    private function exempt(string $path): bool
    {
        foreach (self::EXEMPT as $prefix) {
            $matches = str_ends_with($prefix, '/')
                ? str_starts_with($path, $prefix)
                : $path === $prefix;

            if ($matches) {
                return true;
            }
        }

        return $this->runSubject->engineWrote($path);
    }
}
