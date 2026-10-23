<?php

declare(strict_types=1);

/*
 * Every aic/gates entry starts here (#116): it loads the project's autoloader
 * from the working directory, where composer runs scripts, and never from
 * this file's own directory, which a path repository's link points into
 * AIC's checkout. The entry then asks Gates\Infrastructure\ProjectRoot for
 * the root.
 */

$aicGatesRoot = getcwd();

if ($aicGatesRoot === false) {
    file_put_contents('php://stderr', "aic/gates runs in the project's root, where vendor/autoload.php is.\n");
    exit(2);
}

$aicGatesAutoload = $aicGatesRoot . '/vendor/autoload.php';

if (!is_file($aicGatesAutoload)) {
    file_put_contents('php://stderr', "aic/gates runs in the project's root, where vendor/autoload.php is.\n");
    exit(2);
}

require_once $aicGatesAutoload;
