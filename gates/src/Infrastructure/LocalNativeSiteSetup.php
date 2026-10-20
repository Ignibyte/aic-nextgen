<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Gates\Domain\NativeSiteSetup;
use Gates\Domain\ProcessRunner;

use function array_key_exists;
use function bin2hex;
use function fclose;
use function is_resource;
use function is_string;
use function preg_match;
use function random_bytes;
use function restore_error_handler;
use function set_error_handler;
use function stream_socket_client;
use function stream_socket_get_name;
use function stream_socket_server;

/**
 * Reads where the two sites of gate:23 run on this host. The database is the
 * `PG*` environment when PGPORT is set, otherwise DDEV's published Postgres
 * port (`ddev describe -j`), otherwise 127.0.0.1:5432. The ports are free
 * local ports, asked of the kernel.
 */
final readonly class LocalNativeSiteSetup
{
    /**
     * @param ProcessRunner $processRunner Runs `ddev describe`.
     */
    public function __construct(
        private ProcessRunner $processRunner,
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

        $host = $env['PGHOST'] ?? '127.0.0.1';
        $port = (int) $env['PGPORT'];

        return new NativeSiteSetup($env, $host . ':' . $port, self::answers($host, $port), self::freePort(), self::freePort());
    }

    /**
     * @param string $root Repo-root absolute path.
     *
     * @return int DDEV's published Postgres port; 5432 when DDEV does not say.
     */
    private function ddevPort(string $root): int
    {
        $processResult = $this->processRunner->run(['ddev', 'describe', '-j'], $root);

        return preg_match('/"published_port":\\s*(\\d+)/', $processResult->stdout, $match) === 1
            ? (int) $match[1]
            : 5_432;
    }

    /**
     * @param string $host A host.
     * @param int    $port A port.
     *
     * @return bool Whether something accepts connections there within a second.
     */
    private static function answers(string $host, int $port): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            $socket = stream_socket_client('tcp://' . $host . ':' . $port, timeout: 1.0);
        } finally {
            restore_error_handler();
        }

        if (!is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
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

        return is_string($name) && preg_match('/:(\\d+)$/', $name, $match) === 1
            ? (int) $match[1]
            : 18_090;
    }
}
