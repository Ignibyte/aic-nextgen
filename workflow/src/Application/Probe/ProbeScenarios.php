<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Probe;

use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The sweep's scenarios, `workflow/probes/sweep/<name>/` (#119).
 */
interface ProbeScenarios
{
    /**
     * @return list<string> Every scenario's name, in order: `01-…` to `09-…`, then the `x-…` ones.
     */
    public function names(): array;

    /**
     * @param string $name A scenario.
     *
     * @throws WorkflowRefusal When its `scenario.php` does not return a `ProbeScenario`.
     *
     * @return ProbeScenario It.
     */
    public function load(string $name): ProbeScenario;

    /**
     * @param string $name    A scenario.
     * @param string $fixture One of its files, such as `stray.txt.fixture`.
     *
     * @return string|null Its bytes; null when it has no such file.
     */
    public function fixture(string $name, string $fixture): ?string;
}
