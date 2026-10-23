<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\NativeSiteSetup;
use Gates\Domain\ProcessRunner;
use Gates\Domain\RegexCaptures;

use function array_key_exists;
use function bin2hex;
use function fclose;
use function is_resource;
use function is_string;
use function random_bytes;
use function stream_socket_get_name;
use function stream_socket_server;

use const PHP_BINARY;

/**
 * Reads where the two sites of gate:23 run on this host. The database is the
 * `PG*` environment when PGPORT is set, otherwise DDEV's published Postgres
 * port (`ddev describe -j`), otherwise 127.0.0.1:5432. It counts as reachable
 * when the app's own `bin/aic db:ping` answers with that environment: an open
 * port is not enough, since another Postgres on the host accepts connections
 * and refuses the app. The ports are free local ports, asked of the kernel.
 */
final readonly class LocalNativeSiteSetup
{
    use RegexCaptures;

    /**
     * @param ProcessRunner $processRunner Runs `ddev describe` and the console.
     * @param string        $php           The PHP binary the console runs under.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private string $php = PHP_BINARY,
    ) {
    }

    /**
     * @param array<string, string> $env  The process environment.
     * @param string                $root Repo-root absolute path, where DDEV is asked.
     *
     * @return NativeSiteSetup The setup.
     */
    public function read(array $env, string $root): NativeSiteSetup
    {
        if (!array_key_exists('PGPORT', $env)) {
            $env['PGHOST'] = '127.0.0.1';
            $env['PGPORT'] = (string) $this->ddevPort($root);
        }

        if (!array_key_exists('AIC_APP_SECRET', $env)) {
            $env['AIC_APP_SECRET'] = bin2hex(random_bytes(32));
        }

        $database = ($env['PGHOST'] ?? '127.0.0.1') . ':' . $env['PGPORT'];

        return new NativeSiteSetup($env, $database, $this->answers($root, $env), self::freePort(), self::freePort());
    }

    /**
     * @param string $root Repo-root absolute path.
     *
     * @return int DDEV's published Postgres port; 5432 when DDEV does not say (a stopped project publishes port 0).
     */
    private function ddevPort(string $root): int
    {
        $processResult = $this->processRunner->run(['ddev', 'describe', '-j'], $root);

        return (int) (self::regexMatch('/"published_port":\\s*([1-9]\\d*)/', $processResult->stdout)[1] ?? 5_432);
    }

    /**
     * @param string                $root Repo-root absolute path.
     * @param array<string, string> $env  The environment the sites run with.
     *
     * @return bool Whether the app's database answers `bin/aic db:ping`.
     */
    private function answers(string $root, array $env): bool
    {
        return $this->processRunner->run([$this->php, $root . '/bin/aic', 'db:ping'], $root, $env)->exitCode === 0;
    }

    /**
     * @return int A local port nothing listens on, as the kernel picks it.
     */
    private static function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');

        if (!is_resource($server)) {
            return 18_090;
        }

        $name = stream_socket_get_name($server, false);
        fclose($server);

        return is_string($name)
            ? (int) (self::regexMatch('/:(\\d+)$/', $name)[1] ?? 18_090)
            : 18_090;
    }
}
