<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use AIC\Config\Domain\AppConfig;
use Gates\Domain\NativeSiteSetup;
use Gates\Domain\ProcessRunner;
use Gates\Domain\RegexCaptures;
use RuntimeException;

use function array_key_exists;
use function bin2hex;
use function fclose;
use function is_file;
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
 * and refuses the app. The ports are free local ports, asked of the kernel:
 * one for each site and one for the stub upstream (#305), which both sites
 * reach through AIC_UPSTREAM_STUB with the token `stub-token`. Both sites
 * take the stub webhook's deliveries signed with AIC_WEBHOOK_STUB_SECRET,
 * `stub-webhook-secret` (#321).
 */
final readonly class LocalNativeSiteSetup
{
    use RegexCaptures;

    /**
     * @param ProcessRunner $processRunner Runs `ddev describe` and the console.
     * @param string        $php           The PHP binary the console runs under.
     * @param ProjectConfig $projectConfig Reads the app's body cap from `config/app.php` (#196).
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private string $php = PHP_BINARY,
        private ProjectConfig $projectConfig = new ProjectConfig(),
    ) {
    }

    /**
     * @param array<string, string> $env  The process environment.
     * @param string                $root Repo-root absolute path, where DDEV is asked.
     *
     * @throws RuntimeException When `config/app.php` exists but is not an AppConfig.
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

        $reachable = $this->answers($root, $env);

        [$stubPort, $phpPort, $nativePort] = self::freePorts();
        $env['AIC_UPSTREAM_STUB'] = 'http://127.0.0.1:' . $stubPort;
        $env['AIC_UPSTREAM_STUB_TOKEN'] = 'stub-token';
        $env['AIC_WEBHOOK_STUB_SECRET'] = 'stub-webhook-secret';

        return new NativeSiteSetup($env, $database, $reachable, $phpPort, $nativePort, $this->maxBodyBytes($root), $stubPort);
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
     * @param string $root Repo-root absolute path.
     *
     * @throws RuntimeException When `config/app.php` exists but is not an AppConfig.
     *
     * @return int The app's request body cap; AIC's default when it has no config.
     */
    private function maxBodyBytes(string $root): int
    {
        $file = $root . '/config/app.php';

        return is_file($file)
            ? $this->projectConfig->read($file, AppConfig::class)->maxBodyBytes
            : AppConfig::MAX_BODY_BYTES;
    }

    /**
     * Asks the kernel for three free ports at once: every socket stays open
     * until all three are read, so no two ports are the same (#340). A socket
     * that cannot open or name itself falls back to 18090, 18091 or 18092.
     *
     * @return array{int, int, int} The stub's port, the PHP site's and the native site's.
     */
    private static function freePorts(): array
    {
        $stub = stream_socket_server('tcp://127.0.0.1:0');
        $php = stream_socket_server('tcp://127.0.0.1:0');
        $native = stream_socket_server('tcp://127.0.0.1:0');

        $ports = [
            self::portOf(is_resource($stub) ? stream_socket_get_name($stub, false) : false, 18_090),
            self::portOf(is_resource($php) ? stream_socket_get_name($php, false) : false, 18_091),
            self::portOf(is_resource($native) ? stream_socket_get_name($native, false) : false, 18_092),
        ];

        self::closeAll([$stub, $php, $native]);

        return $ports;
    }

    /**
     * @param list<false|resource> $servers The sockets drawn, a failed one as false.
     */
    private static function closeAll(array $servers): void
    {
        foreach ($servers as $server) {
            if (!is_resource($server)) {
                continue;
            }

            fclose($server);
        }
    }

    /**
     * @param false|string $name     A listening socket's local name, `127.0.0.1:<port>`.
     * @param int          $fallback The port when the name holds none.
     *
     * @return int The port in the name, or the fallback.
     */
    private static function portOf(false|string $name, int $fallback): int
    {
        $port = is_string($name)
            ? (int) (self::regexMatch('/:(\\d+)$/', $name)[1] ?? 0)
            : 0;

        return $port > 0
            ? $port
            : $fallback;
    }
}
