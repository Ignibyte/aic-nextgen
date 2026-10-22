<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What an app's `config/workflow.php` returns (#100): its opt-in to the
 * workflow. #105's installer writes the file; #102 adds the levers.
 *
 * @api
 */
final readonly class WorkflowSettings
{
    /**
     * @param string $tickets The ticket directory, relative to the project, such as `tickets`.
     */
    public function __construct(
        public string $tickets,
    ) {
    }
}
