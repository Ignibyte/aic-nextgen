<?php

declare(strict_types=1);

/* Private session helper: no application autoloading or shell argument interpolation. */

use AIC\Workflow\Infrastructure\SessionCommand;

require __DIR__ . '/../src/Infrastructure/SessionCommand.php';

exit((new SessionCommand())->run(array_slice($argv ?? [], 1)));
