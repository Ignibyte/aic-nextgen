<?php

declare(strict_types=1);

use AIC\Workflow\Infrastructure\Guard\CodexShell;

$root = $argv[1] ?? '';
$autoload = $argv[2] ?? '';
$command = $argv[3] ?? '';

if ($root === '' || $command === '') {
    exit(127);
}

if (!is_file($autoload)) {
    exit(127);
}

require $autoload;
$result = (new CodexShell($root, dirname(__DIR__), $autoload))->run($command);
echo $result->output;
exit($result->exitCode);
