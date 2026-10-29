<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteAnswer;
use Gates\Domain\NativeSiteSetup;
use Gates\Domain\NativeSiteStep;
use Gates\Domain\ProcessResult;
use Gates\Domain\ServerLauncher;
use InvalidArgumentException;
use RuntimeException;

use function array_map;
use function array_slice;
use function count;
use function explode;
use function fwrite;
use function implode;
use function is_file;
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
     * @param NativeSiteBrowser  $nativeSiteBrowser  Runs the kept browser tests against the compiled site (#174).
     * @param NativeSiteScript   $nativeSiteScript   The steps.
     * @param NativeSiteHtml     $nativeSiteHtml     Lints the PHP site's HTML answers (#170).
     * @param NativeSiteCoverage $nativeSiteCoverage Finds the GET routes no step reaches (#171).
     */
    public function __construct(
        private NativeToolchain $nativeToolchain,
        private NativeSiteBuilder $nativeSiteBuilder,
        private ServerLauncher $serverLauncher,
        private NativeSiteComparer $nativeSiteComparer,
        private GateUser $gateUser,
        private NativeSiteBrowser $nativeSiteBrowser,
        private NativeSiteScript $nativeSiteScript = new NativeSiteScript(),
        private NativeSiteHtml $nativeSiteHtml = new NativeSiteHtml(),
        private NativeSiteCoverage $nativeSiteCoverage = new NativeSiteCoverage(),
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

        if (!is_file($root . '/' . NativeSiteScript::PATH) || !$nativeSiteSetup->reachable) {
            fwrite($stdout, $this->deferral($root, $nativeSiteSetup) . PHP_EOL);

            return 0;
        }

        $steps = $this->steps($root, $stderr);
        $workDir = $root . '/.native-site';
        $processResult = $steps === []
            ? null
            : $this->nativeSiteBuilder->build($root, $workDir, $nativeSiteSetup->heapLimit());

        return match (true) {
            $processResult === null => 1,
            $processResult->exitCode !== 0 => $this->buildFailed($processResult, $stderr),
            default => $this->compare($root, $workDir, $nativeSiteSetup, $steps, $stdout, $stderr),
        };
    }

    /**
     * @param string   $root   Repo-root absolute path.
     * @param resource $stderr Stream a bad script is reported to.
     *
     * @return list<NativeSiteStep> The script's steps; none when it is malformed, which is reported.
     */
    private function steps(string $root, mixed $stderr): array
    {
        try {
            return $this->nativeSiteScript->steps($root);
        } catch (InvalidArgumentException $invalidArgumentException) {
            fwrite($stderr, 'gate:23 — ' . $invalidArgumentException->getMessage() . PHP_EOL);

            return [];
        }
    }

    /**
     * @param string          $root            Repo-root absolute path.
     * @param NativeSiteSetup $nativeSiteSetup Where the sites run.
     *
     * @return string Why the gate defers: no script (#130), or no database.
     */
    private function deferral(string $root, NativeSiteSetup $nativeSiteSetup): string
    {
        return is_file($root . '/' . NativeSiteScript::PATH)
            ? sprintf(self::DEFERRAL, $nativeSiteSetup->database)
            : 'gate:23 — deferring the native site: ' . NativeSiteScript::PATH . ' is missing; write the requests both sites must '
                . 'answer alike there (aic.native-site/1), one step a line.';
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
     * @param string               $root            Repo-root absolute path.
     * @param string               $workDir         Where the compiled site is.
     * @param NativeSiteSetup      $nativeSiteSetup Where the sites run.
     * @param list<NativeSiteStep> $steps           The script.
     * @param resource             $stdout          Stream the summary is written to.
     * @param resource             $stderr          Stream failures are written to.
     *
     * @return int The exit code.
     */
    private function compare(
        string $root,
        string $workDir,
        NativeSiteSetup $nativeSiteSetup,
        array $steps,
        mixed $stdout,
        mixed $stderr,
    ): int {
        if (!$this->startSites($root, $workDir, $nativeSiteSetup)) {
            $this->serverLauncher->stopAll();
            fwrite($stderr, 'gate:23 — a site did not start; see ' . $workDir . '/php.log and native.log.' . PHP_EOL);

            return 1;
        }

        $password = $this->gateUser->create($root, $nativeSiteSetup->env);
        $php = $this->nativeSiteComparer->run('http://127.0.0.1:' . $nativeSiteSetup->phpPort, $steps, $password);
        $native = $this->nativeSiteComparer->run('http://127.0.0.1:' . $nativeSiteSetup->nativePort, $steps, $password);
        $nativeOrigin = 'http://127.0.0.1:' . $nativeSiteSetup->nativePort;
        $browser = $this->nativeSiteBrowser->findings($root, $nativeOrigin, $nativeSiteSetup->env, $password);
        $this->serverLauncher->stopAll();

        return $this->judged($root, $steps, [$php, $native], $browser, [$stdout, $stderr]);
    }

    /**
     * @param string                                                $root    Repo-root absolute path.
     * @param list<NativeSiteStep>                                  $steps   The script.
     * @param array{list<NativeSiteAnswer>, list<NativeSiteAnswer>} $answers The PHP site's answers, then the compiled site's.
     * @param array{int, list<string>}                              $browser How many kept browser tests ran, and their failures.
     * @param array{resource, resource}                             $streams Where the summary goes, then the failures.
     *
     * @return int The exit code.
     */
    private function judged(string $root, array $steps, array $answers, array $browser, array $streams): int
    {
        [$php, $native] = $answers;
        $differences = $this->nativeSiteComparer->differences($steps, $php, $native);
        $findings = [
            ...self::prefixed('HTML ', $this->nativeSiteHtml->findings($steps, $php)),
            ...$this->nativeSiteCoverage->unreached($root, $steps, $php),
            ...$browser[1],
        ];

        if ($browser[0] > 0 && $browser[1] === []) {
            fwrite($streams[0], sprintf('gate:23 — browser: %d kept test file(s) pass on the compiled site.%s', $browser[0], PHP_EOL));
        }

        return $this->report($differences, $findings, count($steps), $streams[0], $streams[1]);
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
        $php = [$this->nativeToolchain->phpPath(), ...$nativeSiteSetup->phpLimit()];
        $php = [...$php, '-S', '127.0.0.1:' . $nativeSiteSetup->phpPort, 'public/index.php'];

        $native = [$workDir . '/main', '--listen', '127.0.0.1:' . $nativeSiteSetup->nativePort, '--workers', '2'];
        $native = [...$native, '--handler-concurrency', '2', ...$nativeSiteSetup->nativeLimit()];

        $env = $nativeSiteSetup->env;

        return $this->serverLauncher->start($php, $root, $env, $nativeSiteSetup->phpPort, $workDir . '/php.log')
            && $this->serverLauncher->start($native, $root, $env, $nativeSiteSetup->nativePort, $workDir . '/native.log');
    }

    /**
     * @param list<string> $differences What the sites disagreed on.
     * @param list<string> $findings    What the PHP site's HTML answers break (#170), each GET route no step
     *                                  reaches (#171), and each kept browser test that fails (#174).
     * @param int          $run         How many steps ran.
     * @param resource     $stdout      Stream the summary is written to.
     * @param resource     $stderr      Stream failures are written to.
     *
     * @return int The exit code.
     */
    private function report(array $differences, array $findings, int $run, mixed $stdout, mixed $stderr): int
    {
        foreach ([...self::prefixed('DIFF ', $differences), ...$findings] as $line) {
            fwrite($stderr, 'gate:23 — ' . $line . PHP_EOL);
        }

        if ($differences !== [] || $findings !== []) {
            $summary = 'gate:23 — native site: %d difference(s) from PHP and %d other finding(s) over %d step(s).';
            fwrite($stderr, sprintf($summary, count($differences), count($findings), $run) . PHP_EOL);

            return 1;
        }

        $summary = 'gate:23 — native site: %d of %d step(s) answer as PHP does, reach every GET route, and their HTML passes the lint.';
        fwrite($stdout, sprintf($summary, $run, $run) . PHP_EOL);

        return 0;
    }

    /**
     * @param string       $prefix What each line starts with.
     * @param list<string> $lines  The lines.
     *
     * @return list<string> Each line after the prefix.
     */
    private static function prefixed(string $prefix, array $lines): array
    {
        return array_map(static fn (string $line): string => $prefix . $line, $lines);
    }
}
