<?php

declare(strict_types=1);

use Gates\Application\NoBaselinesGate;

require_once __DIR__ . '/../vendor/autoload.php';

exit((new NoBaselinesGate())->run(dirname(__DIR__)));
