<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The app's scaffolds as they are and as last frozen (#299): every file under
 * `scaffolds/` with its sha256, and the record the engine writes into
 * `var/workflow/scaffolds.json` when a run's complete row passes.
 */
interface ScaffoldLock
{
    /**
     * @return array<string, string> Every file under `scaffolds/` by its path in the project, with its sha256, sorted.
     */
    public function current(): array;

    /**
     * @throws WorkflowRefusal When the record is there and unreadable.
     *
     * @return array<string, string>|null The files as last frozen; null when nothing was frozen yet.
     */
    public function frozen(): ?array;

    /**
     * @param array<string, string> $hashes The files to freeze.
     *
     * @throws WorkflowRefusal When the record cannot be written.
     */
    public function freeze(array $hashes): void;
}
