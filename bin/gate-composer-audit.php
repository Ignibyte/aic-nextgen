<?php

declare(strict_types=1);

use Gates\Application\ComposerAuditGate;
use Gates\Infrastructure\SystemProcessRunner;

require_once __DIR__ . '/../vendor/autoload.php';

exit((new ComposerAuditGate(new SystemProcessRunner()))->run(dirname(__DIR__)));
