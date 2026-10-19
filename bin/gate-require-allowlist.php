<?php

declare(strict_types=1);

use Gates\Application\RequireAllowlistGate;

require_once __DIR__ . '/../vendor/autoload.php';

exit((new RequireAllowlistGate())->run(dirname(__DIR__)));
