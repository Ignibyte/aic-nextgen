<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteSetup;
use Gates\Domain\ServerLauncher;

/**
 * Starts and stops gate:23's two sites (#33, #234): PHP's built-in server
 * over `public/index.php` and the compiled site, from the same build, with
 * an environment each pass chooses; and first the stub upstream both call
 * (#305), PHP's built-in server over the package's `bin/gate-native-site-stub.php`
 * with four workers, so a pooled call does not wait on a slow one.
 */
final readonly class NativeSiteSites
{
    /**
     * The stub upstream's router, in the gates package.
     */
    private const string STUB = '/bin/gate-native-site-stub.php';

    /**
     * @param NativeToolchain $nativeToolchain Names the PHP binary.
     * @param ServerLauncher  $serverLauncher  Starts and stops the servers.
     * @param string          $package         The gates package, which holds the stub.
     */
    public function __construct(
        private NativeToolchain $nativeToolchain,
        private ServerLauncher $serverLauncher,
        private string $package,
    ) {
    }

    /**
     * @param string                $root            The app's root.
     * @param string                $workDir         The native site's work directory.
     * @param NativeSiteSetup       $nativeSiteSetup The ports and limits.
     * @param array<string, string> $env             The environment both sites run with.
     *
     * @return bool Whether the stub and both sites answer on their ports.
     */
    public function start(string $root, string $workDir, NativeSiteSetup $nativeSiteSetup, array $env): bool
    {
        $php = [$this->nativeToolchain->phpPath(), ...$nativeSiteSetup->phpLimit()];
        $php = [...$php, '-S', '127.0.0.1:' . $nativeSiteSetup->phpPort, 'public/index.php'];

        $native = [$workDir . '/main', '--listen', '127.0.0.1:' . $nativeSiteSetup->nativePort];
        $native = [...$native, ...$nativeSiteSetup->nativeCapacity(), ...$nativeSiteSetup->nativeLimit()];

        $stub = [$this->nativeToolchain->phpPath(), '-S', '127.0.0.1:' . $nativeSiteSetup->stubPort, $this->package . self::STUB];
        $stubEnv = [...$env, 'PHP_CLI_SERVER_WORKERS' => '4'];

        return $this->serverLauncher->start($stub, $root, $stubEnv, $nativeSiteSetup->stubPort, $workDir . '/stub.log')
            && $this->serverLauncher->start($php, $root, $env, $nativeSiteSetup->phpPort, $workDir . '/php.log')
            && $this->serverLauncher->start($native, $root, $env, $nativeSiteSetup->nativePort, $workDir . '/native.log');
    }

    /**
     * Stops every server this gate started.
     */
    public function stop(): void
    {
        $this->serverLauncher->stopAll();
    }
}
