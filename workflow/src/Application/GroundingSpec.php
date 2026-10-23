<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeQuery;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_count_values;
use function array_map;
use function count;
use function implode;
use function ksort;
use function sprintf;

/**
 * Grounding's reading of the run's spec (#118): every reason its rows,
 * its entry points and the run's lookups give, or, when there is none, the
 * passing summary with the lookups counted and the twins noted.
 */
final readonly class GroundingSpec
{
    /**
     * The reason while the spec is not there.
     */
    private const string UNWRITTEN = 'the spec is not written yet: rows and entry points wait for it.';

    /**
     * @param SpecFile       $specFile       The run's spec.
     * @param GroundingRules $groundingRules Rule 2.
     * @param GroundingLooks $groundingLooks Rules 1, 3 and 4.
     */
    public function __construct(
        private SpecFile $specFile,
        private GroundingRules $groundingRules,
        private GroundingLooks $groundingLooks,
    ) {
    }

    /**
     * @param RunState        $runState    The run.
     * @param list<CodeQuery> $codeQueries Its lookups at plan.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return list<string> Every reason the plan is not grounded; none when it is.
     */
    public function reasons(RunState $runState, array $codeQueries): array
    {
        $text = $this->specFile->text($runState->runStart->specPath);

        if ($text === null) {
            return [...$this->groundingLooks->looks($codeQueries, [], []), self::UNWRITTEN];
        }

        $specContract = $this->specFile->contract($text);
        $manifestRows = $specContract->manifestRows();
        $entries = $specContract->entries();

        return [
            ...$this->groundingRules->real($manifestRows, $entries),
            ...$this->groundingLooks->looks($codeQueries, $manifestRows, $entries),
        ];
    }

    /**
     * @param RunState        $runState    The run, its plan grounded.
     * @param list<CodeQuery> $codeQueries Its lookups at plan.
     *
     * @throws WorkflowRefusal When a called workflow operation refuses its input or environment.
     *
     * @return string The lookups by command, the rows and entry points resolved, and a note per created class with a twin.
     */
    public function summary(RunState $runState, array $codeQueries): string
    {
        $specContract = $this->specFile->contract($this->specFile->text($runState->runStart->specPath) ?? '');
        $commands = array_count_values(array_map(static fn (CodeQuery $codeQuery): string => $codeQuery->command, $codeQueries));
        ksort($commands);
        $counts = [];

        foreach ($commands as $command => $count) {
            $counts[] = $command . ' ×' . $count;
        }

        $summary = sprintf(
            'plan looked: %d lookups (%s); %d manifest rows and %d entry points resolve.',
            count($codeQueries),
            implode(', ', $counts),
            count($specContract->manifestRows()),
            count($specContract->entries()),
        );

        return implode(' ', [$summary, ...$this->groundingRules->twins($specContract->manifestRows())]);
    }
}
