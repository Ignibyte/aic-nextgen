<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\RuleChanges;
use AIC\Workflow\Application\RunHost;
use AIC\Workflow\Application\RunSubject;
use AIC\Workflow\Domain\RuleChange;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function dirname;
use function file_exists;
use function file_put_contents;
use function str_starts_with;

/**
 * The run's changes as a house rule reads them (#104): each changed path
 * but the engine's own writes, its head the project's file and its base a
 * copy the engine writes under `var/workflow/rule-base/` from the base
 * commit, so a rule runs no git. A copy is named `<path>.base`, so no tool
 * reads it as code.
 */
final readonly class RuleInputs implements RuleChanges
{
    /**
     * Where the base copies go.
     */
    private const string BASE = RunStateFile::DIRECTORY . '/rule-base';

    /**
     * @param string     $root       The project's root.
     * @param RunHost    $runHost    What changed, and the base content.
     * @param RunSubject $runSubject The engine's own writes.
     * @param AtomicFile $atomicFile Makes the copies' directories.
     */
    public function __construct(
        private string $root,
        private RunHost $runHost,
        private RunSubject $runSubject,
        private AtomicFile $atomicFile = new AtomicFile(),
    ) {
    }

    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When the changes cannot be listed or a copy written.
     *
     * @return list<RuleChange> The changes.
     */
    #[Override]
    public function changes(RunState $runState): array
    {
        $changes = [];

        foreach ($this->runHost->changedFiles($runState->runStart->baseCommit) as $path) {
            $engines = str_starts_with($path, RunStateFile::DIRECTORY . '/') || $this->runSubject->engineWrote($path);
            $changes = $engines
                ? $changes
                : [...$changes, $this->change($path, $runState->runStart->baseCommit)];
        }

        return $changes;
    }

    /**
     * @param string      $path       A changed path.
     * @param string|null $baseCommit The base commit.
     *
     * @throws WorkflowRefusal When its base copy cannot be written.
     *
     * @return RuleChange It, with its base copy and its head.
     */
    private function change(string $path, ?string $baseCommit): RuleChange
    {
        $head = $this->root . '/' . $path;

        return new RuleChange($path, $baseCommit === null ? null : $this->base($baseCommit, $path), file_exists($head) ? $head : null);
    }

    /**
     * @param string $baseCommit The base commit.
     * @param string $path       A changed path.
     *
     * @throws WorkflowRefusal When the copy cannot be written.
     *
     * @return string|null The copy of its base content; null when the run added it.
     */
    private function base(string $baseCommit, string $path): ?string
    {
        $content = $this->runHost->fileAt($baseCommit, $path);

        if ($content === null) {
            return null;
        }

        $copy = $this->root . '/' . self::BASE . '/' . $path . '.base';
        $this->atomicFile->directory(dirname($copy));

        if (file_put_contents($copy, $content) === false) {
            throw new WorkflowRefusal('the base copy of ' . $path . ' could not be written.');
        }

        return $copy;
    }
}
