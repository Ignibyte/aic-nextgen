<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteSetup;
use Gates\Domain\ProcessResult;
use Gates\Domain\ServerLauncher;
use RuntimeException;

use function array_slice;
use function count;
use function explode;
use function fwrite;
use function implode;
use function sprintf;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * gate:23 — the native site: the app compiled with `elephc --web` answers every
 * step of the script as the interpreted app does. Both sites run side by side
 * on local ports with the same environment. Every route opens a Postgres
 * session (#41), so when the app's database does not answer the whole gate defers, named,
 * before anything is built: two sites answering 500 alike prove nothing.
 */
final readonly class NativeSiteGate
{
    /**
     * The deferral line when the app's database does not answer, its address in place of `%s`.
     */
    private const string DEFERRAL = 'gate:23 — deferring the native site: the app\'s database does not answer at %s'
        . ' (bin/aic db:ping), and every route opens a Postgres session (#41).';

    /**
     * @param NativeToolchain    $nativeToolchain    The compiler and host PHP.
     * @param NativeSiteBuilder  $nativeSiteBuilder  Builds the compiled site.
     * @param ServerLauncher     $serverLauncher     Runs the two sites.
     * @param NativeSiteComparer $nativeSiteComparer Sends the steps and compares the answers.
     * @param GateUser           $gateUser           Gives the signed-in steps their user.
     * @param NativeSiteScript   $nativeSiteScript   The steps.
     */
    public function __construct(
        private NativeToolchain $nativeToolchain,
        private NativeSiteBuilder $nativeSiteBuilder,
        private ServerLauncher $serverLauncher,
        private NativeSiteComparer $nativeSiteComparer,
        private GateUser $gateUser,
        private NativeSiteScript $nativeSiteScript = new NativeSiteScript(),
    ) {
    }

    /**
     * @param string          $root            Repo-root absolute path.
     * @param NativeSiteSetup $nativeSiteSetup Where the sites run.
     * @param resource        $stdout          Stream the summary is written to.
     * @param resource        $stderr          Stream failures are written to.
     *
     * @throws RuntimeException When the work directory cannot be written.
     *
     * @return int The exit code.
     */
    public function run(
        string $root,
        NativeSiteSetup $nativeSiteSetup,
        mixed $stdout = STDOUT,
        mixed $stderr = STDERR,
    ): int {
        if (!$this->nativeToolchain->isAvailable()) {
            fwrite($stderr, 'gate:23 — elephc not found at ' . $this->nativeToolchain->compilerPath() . ' (set ELEPHC_BIN).' . PHP_EOL);

            return 1;
        }

        if (!$nativeSiteSetup->reachable) {
            fwrite($stdout, sprintf(self::DEFERRAL, $nativeSiteSetup->database) . PHP_EOL);

            return 0;
        }

        $workDir = $root . '/.native-site';
        $processResult = $this->nativeSiteBuilder->build($root, $workDir);

        if ($processResult->exitCode !== 0) {
            return $this->buildFailed($processResult, $stderr);
        }

        return $this->compare($root, $workDir, $nativeSiteSetup, $stdout, $stderr);
    }

    /**
     * Report a site that does not compile: the first lines of the compiler's output.
     *
     * @param ProcessResult $processResult The build's result.
     * @param resource      $stderr        Stream failures are written to.
     *
     * @return int The exit code, 1.
     */
    private function buildFailed(ProcessResult $processResult, mixed $stderr): int
    {
        $output = implode(PHP_EOL, array_slice(explode(PHP_EOL, $processResult->stdout . $processResult->stderr), 0, 12));
        fwrite($stderr, 'gate:23 — the site does not compile natively:' . PHP_EOL . $output . PHP_EOL);

        return 1;
    }

    /**
     * @param string          $root            Repo-root absolute path.
     * @param string          $workDir         Where the compiled site is.
     * @param NativeSiteSetup $nativeSiteSetup Where the sites run.
     * @param resource        $stdout          Stream the summary is written to.
     * @param resource        $stderr          Stream failures are written to.
     *
     * @return int The exit code.
     */
    private function compare(
        string $root,
        string $workDir,
        NativeSiteSetup $nativeSiteSetup,
        mixed $stdout,
        mixed $stderr,
    ): int {
        $steps = $this->nativeSiteScript->steps();

        if (!$this->startSites($root, $workDir, $nativeSiteSetup)) {
            $this->serverLauncher->stopAll();
            fwrite($stderr, 'gate:23 — a site did not start; see ' . $workDir . '/php.log and native.log.' . PHP_EOL);

            return 1;
        }

        $password = $this->gateUser->create($root, $nativeSiteSetup->env);
        $php = $this->nativeSiteComparer->run('http://127.0.0.1:' . $nativeSiteSetup->phpPort, $steps, $password);
        $native = $this->nativeSiteComparer->run('http://127.0.0.1:' . $nativeSiteSetup->nativePort, $steps, $password);
        $this->serverLauncher->stopAll();

        $differences = $this->nativeSiteComparer->differences($steps, $php, $native);

        return $this->report($differences, count($steps), $stdout, $stderr);
    }

    /**
     * @param string          $root            Repo-root absolute path.
     * @param string          $workDir         Where the compiled site is.
     * @param NativeSiteSetup $nativeSiteSetup Where the sites run.
     *
     * @return bool Whether both sites accept connections.
     */
    private function startSites(string $root, string $workDir, NativeSiteSetup $nativeSiteSetup): bool
    {
        $php = [$this->nativeToolchain->phpPath(), '-S', '127.0.0.1:' . $nativeSiteSetup->phpPort, 'public/index.php'];
        $native = [$workDir . '/main', '--listen', '127.0.0.1:' . $nativeSiteSetup->nativePort, '--workers', '2'];
        $native = [...$native, '--handler-concurrency', '2'];

        $env = $nativeSiteSetup->env;

        return $this->serverLauncher->start($php, $root, $env, $nativeSiteSetup->phpPort, $workDir . '/php.log')
            && $this->serverLauncher->start($native, $root, $env, $nativeSiteSetup->nativePort, $workDir . '/native.log');
    }

    /**
     * @param list<string> $differences What the sites disagreed on.
     * @param int          $run         How many steps ran.
     * @param resource     $stdout      Stream the summary is written to.
     * @param resource     $stderr      Stream failures are written to.
     *
     * @return int The exit code.
     */
    private function report(array $differences, int $run, mixed $stdout, mixed $stderr): int
    {
        foreach ($differences as $difference) {
            fwrite($stderr, 'gate:23 — DIFF ' . $difference . PHP_EOL);
        }

        if ($differences !== []) {
            $summary = sprintf('gate:23 — native site: %d difference(s) from PHP over %d step(s).', count($differences), $run);
            fwrite($stderr, $summary . PHP_EOL);

            return 1;
        }

        fwrite($stdout, sprintf('gate:23 — native site: %d of %d step(s) answer as PHP does.%s', $run, $run, PHP_EOL));

        return 0;
    }
}
