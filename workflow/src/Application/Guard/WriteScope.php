<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Application\SpecFile;
use AIC\Workflow\Domain\Guard\Enforcement;
use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\Guard\Hold;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;

use function array_any;
use function in_array;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Where the agent may write in the project (#102), the same for an edit and
 * a shell write. No run open: the ticket directory only (`require_run`).
 * Plan: the run's spec only. Code, check and complete: the spec and each
 * path of its `### File Manifest`, never the build's outputs. Outside the
 * project is always open. Hard refuses, soft nudges once per run, phase and
 * rule, off stands the wall down. While a question waits for the operator,
 * every write in the project is refused at any level (#117).
 */
final readonly class WriteScope
{
    /**
     * What `composer run build` writes.
     */
    private const array BUILD = ['build/', 'generated/', 'public/assets/', 'docs/wiki/'];

    /**
     * What an undeclared path's refusal says after the path.
     */
    private const string UNDECLARED = " is not in the spec's ### File Manifest: add its row (recorded as declared late), then write.";

    /**
     * @param string       $root         The project's root.
     * @param SpecFile     $specFile     The open run's manifest.
     * @param GuardLedger  $guardLedger  Whether a soft rule already nudged.
     * @param PathResolver $pathResolver Where a path lands.
     */
    public function __construct(
        private string $root,
        private SpecFile $specFile,
        private GuardLedger $guardLedger,
        private PathResolver $pathResolver,
    ) {
    }

    /**
     * @param string     $absolute   Where the write lands, absolute.
     * @param GuardScene $guardScene The run and the levers.
     *
     * @return GuardVerdict Allowed, refused or nudged, with the rule.
     */
    public function verdict(string $absolute, GuardScene $guardScene): GuardVerdict
    {
        $relative = $this->relative($absolute);
        $runState = $guardScene->runState;
        $phase = $guardScene->phase();

        if ($relative === null) {
            return new GuardVerdict();
        }

        $waits = self::waits($runState);

        if ($waits !== '') {
            return new GuardVerdict('refuse', 'question-waits', $waits);
        }

        if (!$runState instanceof RunState || !$phase instanceof Phase) {
            return $this->noRun($relative, $guardScene);
        }

        $problem = $this->problem($relative, $runState, $phase);

        return $problem === []
            ? new GuardVerdict()
            : $this->lever($guardScene->enforcement(), $problem[0], $problem[1], $guardScene);
    }

    /**
     * @param string     $relative   A path in the project.
     * @param GuardScene $guardScene The levers.
     *
     * @return GuardVerdict Allowed in the ticket directory; held by `require_run` elsewhere.
     */
    private function noRun(string $relative, GuardScene $guardScene): GuardVerdict
    {
        $tickets = rtrim($guardScene->workflowSettings->tickets, '/');

        if ($tickets !== '' && str_starts_with($relative, $tickets . '/')) {
            return new GuardVerdict();
        }

        return $this->lever(
            $guardScene->workflowSettings->requireRun,
            'require-run',
            'no run is open: begin one with bin/aic workflow:run <ticket> before writing ' . $relative . '.',
            $guardScene,
        );
    }

    /**
     * @param string   $relative A path in the project.
     * @param RunState $runState The open run.
     * @param Phase    $phase    Its phase.
     *
     * @return array{}|array{string, string} The rule and its words when the phase may not write there; empty when it may.
     */
    private function problem(string $relative, RunState $runState, Phase $phase): array
    {
        $spec = $runState->runStart->specPath;

        if ($relative === $spec) {
            return [];
        }

        if ($phase === Phase::Plan) {
            return ['plan-wall', 'plan writes only its spec, ' . $spec . '; ' . $relative . ' waits for code.'];
        }

        if ($this->built($relative)) {
            return ['build-output', $relative . " is the build's: composer run build writes it."];
        }

        return in_array($relative, $this->declared($spec), true)
            ? []
            : ['scope:' . $phase->value, $relative . self::UNDECLARED];
    }

    /**
     * @param Enforcement $enforcement The lever.
     * @param string      $rule        The rule.
     * @param string      $message     Its words.
     * @param GuardScene  $guardScene  The run, for a soft nudge's once.
     *
     * @return GuardVerdict Refused at hard; nudged at soft, in words only the first time; allowed at off.
     */
    private function lever(
        Enforcement $enforcement,
        string $rule,
        string $message,
        GuardScene $guardScene,
    ): GuardVerdict {
        $runState = $guardScene->runState;
        $run = $runState instanceof RunState
            ? $runState->runStart->runId
            : '';
        $phase = $guardScene->phase()->value ?? '';

        return match ($enforcement) {
            Enforcement::Hard => new GuardVerdict('refuse', $rule, $message),
            Enforcement::Soft => new GuardVerdict('nudge', $rule, $this->guardLedger->warned($run, $phase, $rule) ? '' : $message),
            Enforcement::Off => new GuardVerdict(),
        };
    }

    /**
     * @param string $spec The spec's path.
     *
     * @return list<string> The paths its manifest declares.
     */
    private function declared(string $spec): array
    {
        $text = $this->specFile->text($spec);

        return $text === null
            ? []
            : $this->specFile->contract($text)->files();
    }

    /**
     * @param string $relative A path in the project.
     *
     * @return bool Whether the build writes it.
     */
    private function built(string $relative): bool
    {
        return array_any(self::BUILD, static fn (string $prefix): bool => str_starts_with($relative, $prefix));
    }

    /**
     * @param string $absolute An absolute path.
     *
     * @return string|null It relative to the project, as spelled or as landed; null when it lies outside.
     */
    private function relative(string $absolute): ?string
    {
        foreach ([$absolute, $this->pathResolver->landed($absolute)] as $path) {
            if (str_starts_with($path, $this->root . '/')) {
                return substr($path, strlen($this->root) + 1);
            }
        }

        return null;
    }

    /**
     * @param RunState|null $runState The open run.
     *
     * @return string Why nothing is written while it waits for an answer (#117); empty when it does not.
     */
    private static function waits(?RunState $runState): string
    {
        $hold = $runState instanceof RunState
            ? $runState->awaiting
            : null;

        return $hold instanceof Hold && $hold->isQuestion()
            ? 'the run waits for the operator to answer at ' . $hold->phase . ', so nothing is written until then: ' . $hold->question
            : '';
    }
}
