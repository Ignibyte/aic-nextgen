<?php

declare(strict_types=1);

/*
 * The report worker's console, made by bin/aic make:job --worker (#336):
 * `php workers/report/bin/aic.php queue:work [max]` runs the jobs queued on
 * the report queue, interpreted, never in the compiled site. It loads the
 * worker's own install (`composer install --no-dev` in workers/report/), or
 * the app's in development, whose require-dev mirrors the worker's libraries,
 * and reads the app's config/database.php.
 */

use AIC\Console\Application\Console;
use AIC\Controller\Contracts\CommandWriter;
use AIC\Database\Domain\DatabaseConfig;
use AIC\Database\Infrastructure\PdoDatabase;
use AIC\Queue\Application\WorkerKernel;
use Worker\Report\Generated\ReportJobs;

$app = dirname(__DIR__, 3);
$own = dirname(__DIR__) . '/vendor/autoload.php';
$autoload = is_file($own)
    ? $own
    : $app . '/vendor/autoload.php';

if (!is_file($autoload)) {
    file_put_contents('php://stderr', "The report worker has no install: run composer install --no-dev in workers/report/.\n");

    exit(1);
}

require $autoload;

try {
    $databaseConfig = require $app . '/config/database.php';

    if (!$databaseConfig instanceof DatabaseConfig) {
        throw new RuntimeException('config/database.php must return an ' . DatabaseConfig::class . '.');
    }

    $workerKernel = new WorkerKernel(new PdoDatabase($databaseConfig), $app, 'report', new ReportJobs());

    exit((new Console($workerKernel->commands()))->run($argv ?? [], new class () implements CommandWriter {
        /**
         * @param string $text One line, without its line break.
         */
        #[Override]
        public function line(string $text): void
        {
            file_put_contents('php://stdout', $text . "\n");
        }
    }));
} catch (Throwable $throwable) {
    file_put_contents('php://stderr', 'The report worker could not start: ' . $throwable->getMessage() . "\n");

    exit(1);
}
