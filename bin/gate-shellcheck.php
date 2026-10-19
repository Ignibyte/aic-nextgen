<?php

declare(strict_types=1);

use Gates\Application\ShellcheckGate;
use Gates\Infrastructure\SystemProcessRunner;

require_once __DIR__ . '/../vendor/autoload.php';

$isCi = (string) getenv('CI') !== '' || (string) getenv('GITHUB_ACTIONS') !== '';

exit((new ShellcheckGate(new SystemProcessRunner(), $isCi))->run(dirname(__DIR__)));
