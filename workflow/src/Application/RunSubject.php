<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\RunState;

use function array_any;
use function str_starts_with;

/**
 * The subject a verdict judges (#101, Droost's `RunSubject`): the run's
 * changed paths against its base commit, each by content, plus what changes
 * verdicts without showing in the diff: `composer.lock` (ignored here) and the
 * toolchain's files by stat. The engine's own writes (its state, the ticket)
 * are left out, so moving the ticket never stales a verdict.
 */
final readonly class RunSubject
{
    /**
     * @param RunHost        $runHost        What changed.
     * @param SubjectHashing $subjectHashing Hashes it.
     * @param list<string>   $toolchain      Files hashed by stat.
     * @param list<string>   $engineWrites   Path prefixes the engine writes, such as `tickets/`.
     */
    public function __construct(
        private RunHost $runHost,
        private SubjectHashing $subjectHashing,
        private array $toolchain,
        private array $engineWrites,
    ) {
    }

    /**
     * @param RunState $runState The run.
     *
     * @return string The tree's subject hash now.
     */
    public function hash(RunState $runState): string
    {
        $paths = [];

        foreach ($this->runHost->changedFiles($runState->runStart->baseCommit) as $path) {
            if ($this->engineWrote($path)) {
                continue;
            }

            $paths[] = $path;
        }

        return $this->subjectHashing->hash([...$paths, 'composer.lock'], $this->toolchain);
    }

    /**
     * @param string $path A changed path.
     *
     * @return bool Whether the engine writes it.
     */
    public function engineWrote(string $path): bool
    {
        return array_any($this->engineWrites, static fn (string $prefix): bool => str_starts_with($path, $prefix));
    }
}
