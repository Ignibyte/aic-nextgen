<?php

declare(strict_types=1);

/*
 * workflow/bin/aic-guard.php <root> <home> <verb>: the guard's PHP entry
 * (#102), run by aic-guard.sh with the hook's payload on stdin. It loads
 * the autoloader of the project the package is installed in (vendor/aic/
 * workflow), or of the repository it is checked out in, hands the call to
 * the guard, and turns the verdict into the hook's answer: a refusal on
 * stderr and exit 2, a nudge as a systemMessage on stdout, exit 0 otherwise.
 */

use AIC\Workflow\Infrastructure\Guard\GuardKernel;

$installed = dirname(__DIR__, 3) . '/autoload.php';

require is_file($installed)
    ? $installed
    : dirname(__DIR__, 2) . '/vendor/autoload.php';

$guardKernel = new GuardKernel($argv[1] ?? '', $argv[2] ?? '', dirname(__DIR__));
$guardVerdict = $guardKernel->handle($argv[3] ?? '', (string) file_get_contents('php://stdin'));

if ($guardVerdict->refused()) {
    file_put_contents('php://stderr', $guardVerdict->message . "\n");
}

echo $guardKernel->stdout($guardVerdict);

exit($guardVerdict->exitCode());
