<?php

declare(strict_types=1);

use AIC\Workflow\Infrastructure\CodexProtocol;

$root = $argv[1] ?? '';
$autoload = $argv[2] ?? '';

if ($root === '') {
    exit(127);
}

if (!is_file($autoload)) {
    exit(127);
}

require $autoload;

try {
    echo (new CodexProtocol($root))->exchange();
} catch (Throwable $throwable) {
    file_put_contents('php://stderr', $throwable->getMessage() . "\n");
    exit(2);
}
