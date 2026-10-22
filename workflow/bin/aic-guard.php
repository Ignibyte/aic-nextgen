<?php

declare(strict_types=1);

/*
 * workflow/bin/aic-guard.php <root> <home> <verb> <autoload>: the guard's PHP
 * entry (#102), run by aic-guard.sh with the hook's payload on stdin. It
 * loads the autoloader the shim found: the project's, which the package is
 * installed in (vendor/aic/workflow, a symlink when composer took it from a
 * path repository, #105), or the repository's it is checked out in. It hands
 * the call to the guard and turns the verdict into the hook's answer: a
 * refusal on stderr and exit 2, a nudge as a systemMessage on stdout, exit 0
 * otherwise.
 */

use AIC\Workflow\Infrastructure\Guard\GuardKernel;

$autoload = $argv[4] ?? '';

if (!is_file($autoload)) {
    file_put_contents('php://stderr', 'aic-guard: no autoloader at "' . $autoload . "\".\n");

    exit(3);
}

require $autoload;

$guardKernel = new GuardKernel($argv[1] ?? '', $argv[2] ?? '', dirname(__DIR__));
$guardVerdict = $guardKernel->handle($argv[3] ?? '', (string) file_get_contents('php://stdin'));

if ($guardVerdict->refused()) {
    file_put_contents('php://stderr', $guardVerdict->message . "\n");
}

echo $guardKernel->stdout($guardVerdict);

exit($guardVerdict->exitCode());
