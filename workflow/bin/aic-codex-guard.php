<?php

declare(strict_types=1);

use AIC\Workflow\Infrastructure\Guard\CodexKernel;

$autoload = $argv[4] ?? '';

if (!is_file($autoload)) {
    exit(3);
}

require $autoload;

$kernel = new CodexKernel($argv[1] ?? '', $argv[2] ?? '', dirname(__DIR__), $autoload);
echo $kernel->handle($argv[3] ?? '', (string) file_get_contents('php://stdin'));
