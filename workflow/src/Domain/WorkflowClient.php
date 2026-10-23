<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/** The client whose workflow pack the operator installs. */
enum WorkflowClient: string
{
    case Claude = 'claude';
    case Codex = 'codex';
}
