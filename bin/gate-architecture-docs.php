<?php

declare(strict_types=1);

use Gates\Application\ArchitectureDocsGate;

require_once __DIR__ . '/../vendor/autoload.php';

exit((new ArchitectureDocsGate())->run(dirname(__DIR__)));
