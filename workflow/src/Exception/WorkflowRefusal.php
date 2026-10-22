<?php

declare(strict_types=1);

namespace AIC\Workflow\Exception;

use RuntimeException;

/**
 * The workflow refusing a command (#100): its message names the cause and the
 * next step, and the command prints it as one line and exits 2. Nothing is
 * written when a command is refused.
 */
final class WorkflowRefusal extends RuntimeException
{
}
