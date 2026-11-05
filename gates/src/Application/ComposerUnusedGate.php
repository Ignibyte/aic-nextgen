<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;

use function fwrite;
use function implode;
use function is_executable;
use function sprintf;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #4 — composer-unused: every runtime require of the root is used.
 *
 * composer-unused reads the root package's autoload roots. While `modules/`
 * holds no PHP file, every require is unused by definition, so the gate defers
 * and says so instead of failing or filtering the requires away. It runs in
 * full once the demo app has a module (#93).
 */
final readonly class ComposerUnusedGate
{
    /**
     * The demo app's modules (`App\<Module>\` → `modules/<module>/src/`), relative to the repo root.
     */
    private const string APP_ROOT = 'modules';

    /**
     * @param ProcessRunner $processRunner Seam wrapping `proc_open`.
     * @param string        $package       ignibyte/aic-gates' directory: its base configs and elephc pins (#116).
     * @param PhpFileFinder $phpFileFinder Recursive `*.php` directory predicate.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private string $package,
        private PhpFileFinder $phpFileFinder = new PhpFileFinder(),
    ) {
    }

    /**
     * Run composer-unused and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success and deferral lines are written to.
     * @param resource $stderr Stream failure messages are written to.
     *
     * @return int The exit code.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        if (!$this->phpFileFinder->containsPhpFiles($root . '/' . self::APP_ROOT)) {
            fwrite($stdout, sprintf('gate:4 — %s/ has no source files yet; deferring composer-unused.%s', self::APP_ROOT, PHP_EOL));

            return 0;
        }

        $bin = $root . '/vendor/bin/composer-unused';

        if (!is_executable($bin)) {
            fwrite($stderr, sprintf('gate:4 — vendor/bin/composer-unused not found.%sRun: composer install%s', PHP_EOL, PHP_EOL));

            return 1;
        }

        $command = [$bin, '--no-progress', '--configuration=' . $this->package . '/config/composer-unused.php'];
        $processResult = $this->processRunner->run($command);
        fwrite($stdout, sprintf('gate:4 — %s%s%s', implode(' ', $command), PHP_EOL, $processResult->stdout));
        fwrite($stderr, $processResult->stderr);

        return $processResult->exitCode;
    }
}
