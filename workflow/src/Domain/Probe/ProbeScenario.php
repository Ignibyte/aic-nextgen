<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use function array_map;
use function array_sum;

/**
 * One scenario of the probe sweep (#119), `workflow/probes/sweep/<name>/scenario.php`:
 * the app's settings, whether its steps need the database, the files
 * committed with the app's base, and the steps in order. The app gets the
 * scenario's `config.fixture` as `config/workflow.php` when it has one
 * (`{site}` standing for the URL a `ServeStep` serves), else what
 * `workflow:init` wrote, and the shared ticket as ticket 1.
 */
final readonly class ProbeScenario
{
    /**
     * @param list<ProbeStep> $steps    The steps, in order.
     * @param bool            $database Whether they need the database: with none answering, every expectation is not run.
     * @param list<WriteStep> $base     Files committed with the app's base, before any run.
     */
    public function __construct(
        public array $steps,
        public bool $database = false,
        public array $base = [],
    ) {
    }

    /**
     * @return int How many expectations its steps hold.
     */
    public function expectations(): int
    {
        return array_sum(array_map(static fn (ProbeStep $probeStep): int => $probeStep->expectations(), $this->steps));
    }
}
