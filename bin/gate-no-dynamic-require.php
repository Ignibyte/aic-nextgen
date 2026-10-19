<?php

declare(strict_types=1);

use Gates\Application\NoDynamicRequireGate;

require_once __DIR__ . '/../vendor/autoload.php';

exit((new NoDynamicRequireGate())->run(dirname(__DIR__)));
