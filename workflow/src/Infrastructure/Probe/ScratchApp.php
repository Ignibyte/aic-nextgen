<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Workflow\Application\Probe\ProbeFiles;
use AIC\Workflow\Application\Probe\ProbeScenarios;
use AIC\Workflow\Application\Probe\ScratchProject;
use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Infrastructure\LocalProcess;
use Override;

use function fclose;
use function is_resource;
use function str_replace;
use function stream_socket_get_name;
use function stream_socket_server;
use function strrchr;
use function substr;
use function trim;

/**
 * A scenario's app (#119): `workflow/probes/scratch-app.sh make`, the
 * builder the seeker probe shares, then the scenario's `config.fixture` as
 * `config/workflow.php` (`{site}` the URL on a port found free now), the
 * shared ticket as ticket 1 and the scenario's base files, committed as the
 * scenario's base. The package copy sits beside the app, so a plant into it
 * goes with the app.
 */
final readonly class ScratchApp implements ScratchProject
{
    /**
     * The seconds the builder may take: it copies vendor/ and runs init.
     */
    private const int BUILD_TIMEOUT = 1_800;

    /**
     * @param string         $repo           This repository's root, whose working tree the app is made from.
     * @param ProbeScenarios $probeScenarios The scenarios' fixtures.
     * @param ProbeFiles     $probeFiles     Writes into the app.
     */
    public function __construct(
        private string $repo,
        private ProbeScenarios $probeScenarios,
        private ProbeFiles $probeFiles,
    ) {
    }

    /**
     * @param string        $scratch       The folder the apps are made in.
     * @param string        $name          The scenario.
     * @param ProbeScenario $probeScenario Its base files.
     *
     * @throws WorkflowRefusal When the builder fails, a fixture is missing, or the base cannot be committed.
     *
     * @return ProbeApp The app.
     */
    #[Override]
    public function make(string $scratch, string $name, ProbeScenario $probeScenario): ProbeApp
    {
        $probeApp = new ProbeApp($scratch . '/' . $name, $scratch . '/' . $name . '-pkg', $this->freePort());
        $processResult = (new LocalProcess($this->repo))->run(
            ['bash', $this->repo . '/workflow/probes/scratch-app.sh', 'make', $probeApp->root, $probeApp->package],
            self::BUILD_TIMEOUT,
        );

        if ($processResult->exitCode !== 0) {
            throw new WorkflowRefusal('scratch-app.sh make exited ' . $processResult->exitCode . ': ' . trim($processResult->output));
        }

        $config = $this->probeScenarios->fixture($name, 'config.fixture');

        if ($config !== null) {
            $this->put($probeApp, 'config/workflow.php', str_replace('{site}', $probeApp->site(), $config));
        }

        $this->put($probeApp, 'tickets/0001-probe.md', $this->fixture($name, 'ticket.md.fixture'));

        foreach ($probeScenario->base as $writeStep) {
            $this->put($probeApp, $writeStep->path, $this->fixture($name, $writeStep->fixture));
        }

        $this->commit($probeApp);

        return $probeApp;
    }

    /**
     * @param ProbeApp $probeApp An app.
     */
    #[Override]
    public function remove(ProbeApp $probeApp): void
    {
        (new LocalProcess($this->repo))->run(['rm', '-rf', $probeApp->root, $probeApp->package], 600);
    }

    /**
     * @param string $name    The scenario.
     * @param string $fixture One of its fixtures.
     *
     * @throws WorkflowRefusal When it has no such file.
     *
     * @return string Its bytes.
     */
    private function fixture(string $name, string $fixture): string
    {
        return $this->probeScenarios->fixture($name, $fixture)
            ?? throw new WorkflowRefusal('workflow/probes/sweep/' . $name . ' has no ' . $fixture . '.');
    }

    /**
     * @param ProbeApp $probeApp The app.
     * @param string   $path     A path in it.
     * @param string   $bytes    What it holds.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    private function put(ProbeApp $probeApp, string $path, string $bytes): void
    {
        if (!$this->probeFiles->write($probeApp, $path, $bytes)) {
            throw new WorkflowRefusal($path . ' could not be written in ' . $probeApp->root . '.');
        }
    }

    /**
     * @param ProbeApp $probeApp The app.
     *
     * @throws WorkflowRefusal When the base cannot be committed.
     */
    private function commit(ProbeApp $probeApp): void
    {
        $localProcess = new LocalProcess($probeApp->root);
        $added = $localProcess->run(['git', 'add', '-A'], 120)->exitCode;
        $committed = $localProcess->run(['git', 'commit', '-qm', 'probe base', '--no-verify'], 120)->exitCode;

        if ($added !== 0 || $committed !== 0) {
            throw new WorkflowRefusal('the scenario base could not be committed in ' . $probeApp->root . '.');
        }
    }

    /**
     * @throws WorkflowRefusal When no port can be had.
     *
     * @return int A port free on 127.0.0.1 now.
     */
    private function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');

        if (!is_resource($server)) {
            throw new WorkflowRefusal('no free port on 127.0.0.1 for the app.');
        }

        $address = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr((string) strrchr($address, ':'), 1);
    }
}
