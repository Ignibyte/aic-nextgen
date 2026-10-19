<?php

declare(strict_types=1);

use Gates\Application\DocTodosGate;

require_once __DIR__ . '/../vendor/autoload.php';

exit((new DocTodosGate())->run(dirname(__DIR__)));
