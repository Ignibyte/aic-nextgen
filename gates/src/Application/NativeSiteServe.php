<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteSetup;
use RuntimeException;

use function fwrite;
use function is_resource;
use function proc_close;
use function proc_open;

use const PHP_EOL;
use const STDERR;
use const STDIN;
use const STDOUT;

/**
 * `composer run native:serve [port]`: builds the compiled site and serves it
 * on 127.0.0.1 in the foreground, with its access log, until interrupted, so a
 * browser can look at it. The database is the one gate:23 uses.
 */
final readonly class NativeSiteServe
{
    /**
     * @param NativeSiteBuilder $nativeSiteBuilder Builds the compiled site.
     */
    public function __construct(
        private NativeSiteBuilder $nativeSiteBuilder,
    ) {
    }

    /**
     * @param string          $root            Repo-root absolute path.
     * @param NativeSiteSetup $nativeSiteSetup The environment and the database.
     * @param string          $port            The local port to listen on.
     *
     * @throws RuntimeException When the work directory cannot be written.
     *
     * @return int The server's exit code; 1 when it could not be built or started.
     */
    public function serve(string $root, NativeSiteSetup $nativeSiteSetup, string $port): int
    {
        $workDir = $root . '/.native-site';
        $processResult = $this->nativeSiteBuilder->build($root, $workDir);

        if ($processResult->exitCode !== 0) {
            fwrite(STDERR, $processResult->stdout . $processResult->stderr);

            return 1;
        }

        fwrite(STDOUT, 'native:serve — http://127.0.0.1:' . $port . '/ (database ' . $nativeSiteSetup->database . ')' . PHP_EOL);
        $command = [$workDir . '/main', '--listen', '127.0.0.1:' . $port, '--workers', '2', '--handler-concurrency', '2', '--access-log'];
        $server = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root, $nativeSiteSetup->env);

        return is_resource($server) && $pipes === []
            ? proc_close($server)
            : 1;
    }
}
