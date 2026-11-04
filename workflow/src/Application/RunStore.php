<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Where the run lives (#100): one open run per project, its archive once it
 * ends or is reset. The engine is its only writer.
 */
interface RunStore
{
    /**
     * @throws WorkflowRefusal When the run's file is there but unreadable, corrupt or of another version.
     *
     * @return RunState|null The open run; none when there is no run.
     */
    public function load(): ?RunState;

    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    public function save(RunState $runState): void;

    /**
     * @param RunState $runState The run, ended or abandoned.
     *
     * @throws WorkflowRefusal When it cannot be archived.
     *
     * @return string Where it went, relative to the project.
     */
    public function archive(RunState $runState): string;

    /**
     * @throws WorkflowRefusal When the newest archived run is unreadable.
     *
     * @return RunState|null The most recently archived run; none when there is none.
     */
    public function lastArchived(): ?RunState;

    /**
     * @param RunState $runState The open run.
     * @param string   $text     Its spec, whole, as the operator's amendment left it (#267).
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    public function saveSpec(RunState $runState, string $text): void;
}
