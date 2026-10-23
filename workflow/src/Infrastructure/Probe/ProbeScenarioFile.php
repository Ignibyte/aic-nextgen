<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Workflow\Application\Probe\ProbeScenarios;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function is_array;
use function is_file;
use function sort;

/**
 * The scenarios on disk (#119): one folder per scenario under
 * `workflow/probes/sweep/`, its `scenario.php` returning a `ProbeScenario`
 * and its plants in `*.fixture` files beside it. The package runs
 * interpreted, so a scenario is required by its path, as the settings file
 * is.
 */
final readonly class ProbeScenarioFile implements ProbeScenarios
{
    /**
     * @param string $folder `workflow/probes/sweep`, absolute.
     */
    public function __construct(
        private string $folder,
    ) {
    }

    /**
     * @return list<string> Every scenario's name, numbered ones first, as their folders sort.
     */
    #[Override]
    public function names(): array
    {
        $names = [];

        $paths = glob($this->folder . '/*/scenario.php');

        foreach (is_array($paths) ? $paths : [] as $path) {
            $names[] = basename(dirname($path));
        }

        sort($names);

        return $names;
    }

    /**
     * @param string $name A scenario.
     *
     * @throws WorkflowRefusal When its `scenario.php` does not return a `ProbeScenario`.
     *
     * @return ProbeScenario It.
     */
    #[Override]
    public function load(string $name): ProbeScenario
    {
        $path = $this->folder . '/' . $name . '/scenario.php';
        $probeScenario = [is_file($path)
            ? require $path
            : null];

        return $probeScenario[0] instanceof ProbeScenario
            ? $probeScenario[0]
            : throw new WorkflowRefusal('workflow/probes/sweep/' . $name . '/scenario.php returns no ProbeScenario.');
    }

    /**
     * @param string $name    A scenario.
     * @param string $fixture One of its files.
     *
     * @return string|null Its bytes; null when it has no such file. A name with no folder in it falls back to the
     *                     sweep's shared fixtures, such as `ticket.md.fixture`.
     */
    #[Override]
    public function fixture(string $name, string $fixture): ?string
    {
        foreach ([$this->folder . '/' . $name . '/' . $fixture, $this->folder . '/' . $fixture] as $path) {
            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        return null;
    }
}
