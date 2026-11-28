<?php

declare(strict_types=1);

/*
 * socket.php: run the socket server (#32).
 *
 * Compiled with the class bundle as a plain elephc program (gate:23 builds it
 * as `.native-site/socket/main`), it is the socket server a deployment runs
 * beside the compiled site; `bin/aic socket:serve` is the same command
 * interpreted. It reads config/database.php, config/mail.php and
 * config/app.php (typed), wires the console kernel
 * (AIC\Module\Application\ConsoleKernel) over the generated wiring
 * (App\Generated\ConsoleModules) and runs `socket:serve` with the program's
 * arguments (`--host=`, `--port=`, `--seconds=`). The exit code is the
 * command's; a configuration that cannot start it prints why and exits 1.
 */

use AIC\Config\Domain\AppConfig;
use AIC\Console\Application\Console;
use AIC\Console\Infrastructure\StdoutCommandWriter;
use AIC\Database\Domain\DatabaseConfig;
use AIC\Database\Infrastructure\PdoDatabase;
use AIC\Module\Application\ConsoleKernel;
use AIC\Notification\Domain\MailConfig;
use AIC\Notification\Infrastructure\SmtpTransport;
use App\Generated\ConsoleModules;

try {
    $databaseConfig = require __DIR__ . '/../config/database.php';

    if (!$databaseConfig instanceof DatabaseConfig) {
        throw new RuntimeException('config/database.php must return an ' . DatabaseConfig::class . '.');
    }

    $mailConfig = require __DIR__ . '/../config/mail.php';

    if (!$mailConfig instanceof MailConfig) {
        throw new RuntimeException('config/mail.php must return an ' . MailConfig::class . '.');
    }

    $appConfig = require __DIR__ . '/../config/app.php';

    if (!$appConfig instanceof AppConfig) {
        throw new RuntimeException('config/app.php must return an ' . AppConfig::class . '.');
    }

    $consoleKernel = new ConsoleKernel(
        new PdoDatabase($databaseConfig),
        new SmtpTransport($mailConfig),
        $mailConfig,
        new ConsoleModules(),
        __DIR__ . '/..',
        $appConfig,
    );
    $serve = $consoleKernel->commands()['socket:serve'] ?? null;

    if ($serve === null) {
        throw new RuntimeException('The console kernel holds no socket:serve.');
    }

    $arguments = ['socket', 'socket:serve'];

    foreach (array_slice($argv ?? [], 1) as $argument) {
        $arguments[] = $argument;
    }

    exit((new Console(['socket:serve' => $serve]))->run($arguments, new StdoutCommandWriter()));
} catch (Throwable $throwable) {
    echo 'The socket server could not start: ', $throwable->getMessage(), "\n";

    exit(1);
}
