<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CheckRow;
use AIC\Workflow\Domain\CheckStatus;
use AIC\Workflow\Domain\DeclaredEntry;
use AIC\Workflow\Domain\Findings;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RenderedAnswer;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_any;
use function rtrim;
use function str_contains;
use function str_starts_with;

/**
 * The rendered check (#101): the engine requests each GET route the spec
 * declares from the site in `config/workflow.php` and holds it to the
 * declared status (200 unless declared) and its kind's content type, or, when
 * the declared status is a redirect, to a Location (#176): the framework writes
 * every redirect as plain text, whatever the kind. A route
 * that does not answer, or cannot be requested, holds the phase too. Each
 * route is recorded as `rendered GET /path`; native parity stays with gate:23.
 */
final readonly class RenderedCheck implements PhaseCheck
{
    /**
     * Each kind's content type; a stream's is its own.
     */
    private const array TYPES = ['Api' => 'application/json', 'Page' => 'text/html'];

    /**
     * Why routes cannot be requested: no site is set.
     */
    private const string NO_SITE = 'the spec declares GET routes, and config/workflow.php sets no site to request them from.';

    /**
     * What the operator does about it: the site is theirs, so the agent waits.
     */
    private const string SET_SITE = "the operator sets site in config/workflow.php to the app's URL, "
        . 'then runs bin/aic workflow:run from a terminal.';

    /**
     * What to do about a route that did not answer as declared.
     */
    private const string REMEDY = 'make each route answer as its ## Entry Points row declares, then run bin/aic workflow:run again.';

    /**
     * @param Phase            $phase            The phase it runs at.
     * @param string           $site             The site's base URL; empty when there is none.
     * @param SiteProbe        $siteProbe        Sends the requests.
     * @param SpecFile         $specFile         The declared routes.
     * @param EvidenceRecorder $evidenceRecorder Records each verdict.
     * @param RunSubject       $runSubject       The tree's subject hash.
     */
    public function __construct(
        private Phase $phase,
        private string $site,
        private SiteProbe $siteProbe,
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
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return CheckRow Passed when every declared GET route answered as declared; failed, naming each that did not; held
     *                  for the operator when routes are declared and no site is set (#105).
     */
    #[Override]
    public function check(RunState $runState): CheckRow
    {
        $entries = $this->specFile->entries($runState->runStart->specPath);

        $routes = array_any($entries, static fn (DeclaredEntry $declaredEntry): bool => $declaredEntry->method === 'GET');

        if ($this->site === '' && $routes) {
            return new CheckRow('rendered', CheckStatus::Held, self::NO_SITE, self::SET_SITE);
        }

        $subjectHash = $this->runSubject->hash($runState);
        $failures = [];

        foreach ($entries as $entry) {
            $failures = [...$failures, ...$this->route($runState, $entry, $subjectHash)];
        }

        $findings = new Findings('rendered', $failures, 'every declared GET route answered as declared.', $subjectHash);

        return $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), self::REMEDY);
    }

    /**
     * @param RunState      $runState      The run.
     * @param DeclaredEntry $declaredEntry A declared entry point.
     * @param string        $subjectHash   The tree.
     *
     * @return list<string> Its failure, recorded as `rendered GET /path`; none when it answered, or is no GET route.
     */
    private function route(RunState $runState, DeclaredEntry $declaredEntry, string $subjectHash): array
    {
        if ($declaredEntry->method !== 'GET') {
            return [];
        }

        $path = $declaredEntry->example === ''
            ? $declaredEntry->name
            : $declaredEntry->example;
        $problem = $this->problem($declaredEntry, $path);
        $findings = new Findings(
            'rendered GET ' . $declaredEntry->name,
            $problem === '' ? [] : ['GET ' . $path . ': ' . $problem],
            'GET ' . $path . ' answered as declared.',
            $subjectHash,
        );
        $checkRow = $this->evidenceRecorder->row($runState, $this->phase, $findings->record(), self::REMEDY);

        return $checkRow->checkStatus->holds()
            ? [$checkRow->why]
            : [];
    }

    /**
     * @param DeclaredEntry $declaredEntry A declared GET route.
     * @param string        $path          The path requested.
     *
     * @return string What is wrong with its answer; empty when nothing is.
     */
    private function problem(DeclaredEntry $declaredEntry, string $path): string
    {
        if (str_contains($path, '{')) {
            return 'its row has no Example path.';
        }

        $renderedAnswer = $this->siteProbe->get(rtrim($this->site, '/') . $path);
        $type = self::TYPES[$declaredEntry->kind] ?? '';

        return match (true) {
            !$renderedAnswer instanceof RenderedAnswer => 'nothing answered at ' . $this->site . '.',
            $renderedAnswer->status !== $declaredEntry->status => 'answered ' . $renderedAnswer->status
                . ', declared ' . $declaredEntry->status . '.',
            self::redirect($declaredEntry->status) => $renderedAnswer->location === ''
                ? 'answered ' . $renderedAnswer->status . ' without a Location.'
                : '',
            $type !== '' && !str_starts_with($renderedAnswer->contentType, $type) => 'answered ' . $renderedAnswer->contentType
                . ', not ' . $type . '.',
            default => '',
        };
    }

    /**
     * @param int $status A declared status.
     *
     * @return bool Whether it is a redirect, held to its Location rather than a content type.
     */
    private static function redirect(int $status): bool
    {
        return $status >= 300 && $status < 400;
    }
}
