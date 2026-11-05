<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * What `workflow:init` asks of the project around the pack (#105): who runs
 * it, whether a run is open, how composer declares the workflow, and the
 * settings it installs or finds.
 */
interface InstallHost
{
    /**
     * @return bool Whether standard input is a terminal: the operator, not an agent's shell.
     */
    public function operatorTerminal(): bool;

    /**
     * @return bool Whether a run is open (`var/workflow/run.json`).
     */
    public function runOpen(): bool;

    /**
     * @return string What is wrong with composer.json's declaration of `ignibyte/aic-workflow`; empty when it is in `require-dev`.
     */
    public function composerProblem(): string;

    /**
     * @throws WorkflowRefusal When `config/workflow.php` is there and does not load.
     *
     * @return string The ticket directory: the settings' when they exist, `tickets` before.
     */
    public function tickets(): string;

    /**
     * @throws WorkflowRefusal When `config/workflow.php` does not load.
     *
     * @return list<string> The settings as the engine reads them, a line each.
     */
    public function settings(): array;
}
