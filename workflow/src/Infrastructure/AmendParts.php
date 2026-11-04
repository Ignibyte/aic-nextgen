<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\AmendContext;
use AIC\Workflow\Application\SpecAmend;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

/**
 * `workflow:amend`'s parts (#267), built at the command from the project's `config/workflow.php`, as
 * the workflow kernel builds its own.
 */
final readonly class AmendParts implements AmendContext
{
    /**
     * @param string       $root         The project's root: the directory `bin/aic` runs in.
     * @param SettingsFile $settingsFile Reads `config/workflow.php`.
     */
    public function __construct(
        private string $root,
        private SettingsFile $settingsFile = new SettingsFile(),
    ) {
    }

    /**
     * @throws WorkflowRefusal When the workflow is not installed in this project.
     *
     * @return SpecAmend The operator's amendment of a frozen spec row.
     */
    #[Override]
    public function specAmend(): SpecAmend
    {
        return (new WorkflowParts($this->root, $this->settingsFile->read($this->root)))->holdParts()->specAmend();
    }
}
