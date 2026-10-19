<?php

declare(strict_types=1);

use Gates\Application\ComposerUnusedGate;
use Gates\Infrastructure\SystemProcessRunner;

require_once __DIR__ . '/../vendor/autoload.php';

exit((new ComposerUnusedGate(new SystemProcessRunner()))->run(dirname(__DIR__)));
