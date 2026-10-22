<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\Guard\HookCall;

/**
 * The wall on the editors (#102): Write, Edit, MultiEdit and NotebookEdit,
 * and every MCP tool whose input names a path (`path`, `file_path`, `file`,
 * `paths`; the driver's decision). Each path is checked against the
 * protected list, as spelled and as landed, then against the phase's
 * scope.
 */
final readonly class EditWall
{
    /**
     * @param ProtectedPaths $protectedPaths What is never written.
     * @param WriteScope     $writeScope     Where the phase writes.
     * @param PathResolver   $pathResolver   Where a path lands.
     */
    public function __construct(
        private ProtectedPaths $protectedPaths,
        private WriteScope $writeScope,
        private PathResolver $pathResolver,
    ) {
    }

    /**
     * @param HookCall   $hookCall   An editor's or an MCP tool's call.
     * @param GuardScene $guardScene The run and the levers.
     *
     * @return GuardVerdict Refused at the first path that hits a wall; nudged or allowed otherwise.
     */
    public function check(HookCall $hookCall, GuardScene $guardScene): GuardVerdict
    {
        $verdict = new GuardVerdict();

        foreach ($hookCall->paths as $path) {
            $fresh = $this->path($path, $hookCall->cwd, $guardScene);

            if ($fresh->refused()) {
                return $fresh;
            }

            $verdict = $verdict->verdict === 'allow'
                ? $fresh
                : $verdict;
        }

        return $verdict;
    }

    /**
     * @param string     $path       A path the call names.
     * @param string     $cwd        Where it resolves.
     * @param GuardScene $guardScene The run and the levers.
     *
     * @return GuardVerdict Its verdict: protected, then the phase's scope.
     */
    private function path(string $path, string $cwd, GuardScene $guardScene): GuardVerdict
    {
        $absolute = $this->pathResolver->absolute($path, $cwd);
        $why = $this->protectedPaths->reason($absolute, $this->protectedPaths->spec($guardScene));

        return $why === ''
            ? $this->writeScope->verdict($absolute, $guardScene)
            : new GuardVerdict('refuse', 'protected', $path . ': ' . $why);
    }
}
