<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Workflow\Application\Probe\ProbeServer;
use AIC\Workflow\Domain\Probe\ProbeApp;
use AIC\Workflow\Infrastructure\LocalProcess;
use Override;

use function bin2hex;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fsockopen;
use function is_file;
use function is_resource;
use function preg_match;
use function random_bytes;
use function restore_error_handler;
use function set_error_handler;
use function trim;
use function unlink;
use function usleep;

/**
 * The app served for the rendered check (#119): `php -S` on the app's port
 * with `public/index.php` as the router, as gate:23 serves PHP, under a
 * fresh `AIC_APP_SECRET`. Its process id is kept in the app's
 * `var/probe-site.pid` and it is stopped by that id with `kill`, never by a
 * pattern, which can take the caller's own shell with it.
 */
final readonly class ProbeSite implements ProbeServer
{
    /**
     * The process id file, relative to the app.
     */
    private const string PID = 'var/probe-site.pid';

    /**
     * @param ProbeApp              $probeApp The app.
     * @param array<string, string> $env      Variables it serves with.
     *
     * @return bool Whether it answers within ten seconds.
     */
    #[Override]
    public function start(ProbeApp $probeApp, array $env): bool
    {
        $variables = ['AIC_APP_SECRET=' . bin2hex(random_bytes(32))];

        foreach ($env as $name => $value) {
            $variables[] = $name . '=' . $value;
        }

        $serve = ['env', ...$variables, 'php', '-S', '127.0.0.1:' . $probeApp->port, 'public/index.php'];
        $processResult = (new LocalProcess($probeApp->root))->run(
            ['sh', '-c', 'nohup "$@" > var/probe-site.log 2>&1 & echo $!', 'sh', ...$serve],
            30,
        );
        $pid = trim($processResult->output);

        if (preg_match('/^\\d+$/', $pid) !== 1) {
            return false;
        }

        file_put_contents($probeApp->root . '/' . self::PID, $pid);

        return $this->wait($probeApp, true);
    }

    /**
     * @param ProbeApp $probeApp The app.
     *
     * @return bool Whether its server is gone; true when none was started.
     */
    #[Override]
    public function stop(ProbeApp $probeApp): bool
    {
        $file = $probeApp->root . '/' . self::PID;

        if (!is_file($file)) {
            return true;
        }

        (new LocalProcess($probeApp->root))->run(['kill', trim((string) file_get_contents($file))], 10);
        unlink($file);

        return $this->wait($probeApp, false);
    }

    /**
     * @param ProbeApp $probeApp  The app.
     * @param bool     $answering Whether to wait for its port to answer, or to stop answering.
     *
     * @return bool Whether it did within ten seconds.
     */
    private function wait(ProbeApp $probeApp, bool $answering): bool
    {
        $tries = 100;

        while ($tries > 0) {
            $answers = $this->answers($probeApp->port);

            if ($answers === $answering) {
                return true;
            }

            usleep(100_000);
            $tries -= 1;
        }

        return false;
    }

    /**
     * @param int $port A port on 127.0.0.1.
     *
     * @return bool Whether it takes a connection; a refusal is an answer, not a warning.
     */
    private function answers(int $port): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            $socket = fsockopen('127.0.0.1', $port, timeout: 0.2);
        } finally {
            restore_error_handler();
        }

        if (!is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
