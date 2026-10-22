<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\WorkflowSettings;
use AIC\Workflow\Exception\WorkflowRefusal;

use function in_array;
use function is_file;

/**
 * An app's opt-in, `config/workflow.php` (#100): it returns WorkflowSettings.
 * Without it the workflow is not installed in the project, and every command
 * says so; #105's `workflow:init` writes it.
 */
final readonly class SettingsFile
{
    /**
     * @param string $root The project's root.
     *
     * @throws WorkflowRefusal When the file is missing, or returns anything but WorkflowSettings.
     *
     * @return WorkflowSettings The settings.
     */
    public function read(string $root): WorkflowSettings
    {
        $path = $root . '/config/workflow.php';

        if (!is_file($path)) {
            throw new WorkflowRefusal('the workflow is not installed here (no config/workflow.php); bin/aic workflow:init installs it.');
        }

        $workflowSettings = require $path;

        if (!$workflowSettings instanceof WorkflowSettings) {
            throw new WorkflowRefusal('config/workflow.php returns ' . WorkflowSettings::class . ', and here it does not.');
        }

        $this->houseRules($workflowSettings->houseRules);

        return $workflowSettings;
    }

    /**
     * @param list<HouseRule> $houseRules The rules config/workflow.php names (#104).
     *
     * @throws WorkflowRefusal When two share an id.
     */
    private function houseRules(array $houseRules): void
    {
        $ids = [];

        foreach ($houseRules as $houseRule) {
            if (in_array($houseRule->id, $ids, true)) {
                throw new WorkflowRefusal('config/workflow.php names house rule ' . $houseRule->id . ' twice.');
            }

            $ids[] = $houseRule->id;
        }
    }
}
