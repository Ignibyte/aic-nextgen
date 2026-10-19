<?php

declare(strict_types=1);

use Gates\Application\PipelineSpecSyncGate;

require_once __DIR__ . '/../vendor/autoload.php';

exit((new PipelineSpecSyncGate())->run(dirname(__DIR__)));
