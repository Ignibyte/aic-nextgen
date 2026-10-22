<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Guard\EditWall;
use AIC\Workflow\Application\Guard\GuardLedger;
use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\Guard\GuardRow;
use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

/**
 * The guard's word on a make's writes (#122). A write made inside bin/aic is
 * one the hook never sees, so the make asks the same `EditWall` for each
 * target, the manifest included, when the project has `config/workflow.php`:
 * a refusal is the make's, a soft nudge is printed and the make goes on, off
 * allows. Each verdict is a row in the guard's ledger (hook `make`), so a
 * soft nudge is printed once per rule and phase, as the hook's is. Without
 * the file there is no wall (AIC's own repository), never the hard defaults
 * the guard falls back to.
 */
final readonly class MakeScope
{
    /**
     * @param string                 $root        The project's root.
     * @param EditWall               $editWall    The guard's edit wall.
     * @param GuardLedger            $guardLedger The guard's ledger.
     * @param UtcTime                $utcTime     Now, for its rows.
     * @param Closure(): ?GuardScene $scene       The settings and the run, read when asked; null without `config/workflow.php`.
     */
    public function __construct(
        private string $root,
        private EditWall $editWall,
        private GuardLedger $guardLedger,
        private UtcTime $utcTime,
        private Closure $scene,
    ) {
    }

    /**
     * @param string       $command The make, such as `make:page`.
     * @param list<string> $paths   The make's targets, relative to the root.
     *
     * @throws WorkflowRefusal When the guard refuses one, naming its rule.
     *
     * @return list<string> The nudges to print; none when the guard allows every one.
     */
    public function check(string $command, array $paths): array
    {
        $guardScene = ($this->scene)();

        if (!$guardScene instanceof GuardScene) {
            return [];
        }

        $nudges = array_map(fn (string $path): string => $this->nudge($command, $path, $guardScene), $paths);

        return array_values(array_unique(array_filter($nudges, static fn (string $nudge): bool => $nudge !== '')));
    }

    /**
     * @return bool Whether the guard watches the makes, so an open run records them: the project has
     *              `config/workflow.php` and the makes are not gate:31's in memory (#119).
     */
    public function watched(): bool
    {
        return ($this->scene)() instanceof GuardScene;
    }

    /**
     * @param string     $command    The make.
     * @param string     $path       A target, relative to the root.
     * @param GuardScene $guardScene The guard's settings and run.
     *
     * @throws WorkflowRefusal When the guard refuses it, naming its rule.
     *
     * @return string The guard's nudge on it; empty for none.
     */
    private function nudge(string $command, string $path, GuardScene $guardScene): string
    {
        $guardVerdict = $this->editWall->landing($this->root . '/' . $path, $guardScene, $path);
        $this->ledger($command, $guardVerdict, $guardScene);

        if ($guardVerdict->refused()) {
            throw new WorkflowRefusal('the guard refuses ' . $path . ' (' . $guardVerdict->rule . '): ' . $guardVerdict->message);
        }

        return $guardVerdict->message;
    }

    /**
     * @param string       $command      The make.
     * @param GuardVerdict $guardVerdict The verdict on one target.
     * @param GuardScene   $guardScene   The run.
     */
    private function ledger(string $command, GuardVerdict $guardVerdict, GuardScene $guardScene): void
    {
        $runState = $guardScene->runState;
        $run = $runState instanceof RunState
            ? $runState->runStart->runId
            : '';

        $this->guardLedger->append(new GuardRow(
            $this->utcTime->now(),
            $run,
            $guardScene->phase()->value ?? '',
            'make',
            $command,
            $guardVerdict->verdict,
            $guardVerdict->rule,
            '',
        ));
    }
}
