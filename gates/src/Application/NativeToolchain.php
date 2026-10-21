<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessJob;
use Gates\Domain\ProcessResult;
use Gates\Domain\ProcessRunner;

use function dirname;
use function glob;
use function implode;
use function is_executable;
use function sprintf;
use function stat;

use const PHP_BINARY;

/**
 * The elephc toolchain the native gate drives (gate:21): the compiler, its
 * environment, and host PHP as the oracle.
 *
 * The compiler runs in place, beside its bridge libraries (a copy elsewhere
 * fails to link). The environment is the caller's, plus a run-local
 * `XDG_CACHE_HOME` and `ELEPHC_NATIVE_CACHE` for the managed native packages;
 * `TMPDIR` is left as the box sets it, because it is part of the native
 * toolchain's fingerprint.
 */
final readonly class NativeToolchain
{
    /**
     * Seconds a native binary or PHP may run before it counts as failed.
     */
    private const string RUN_TIMEOUT = '20';

    /**
     * @param ProcessRunner         $processRunner Seam wrapping `proc_open`.
     * @param string                $elephc        Absolute path to the elephc binary.
     * @param array<string, string> $env           Full environment for every child process.
     * @param string                $php           Host PHP binary, the oracle.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private string $elephc,
        private array $env,
        private string $php = PHP_BINARY,
    ) {
    }

    /**
     * Whether the elephc binary is there to run.
     *
     * @return bool True when the binary is executable.
     */
    public function isAvailable(): bool
    {
        return is_executable($this->elephc);
    }

    /**
     * The compiler path, for messages.
     *
     * @return string The elephc binary path.
     */
    public function compilerPath(): string
    {
        return $this->elephc;
    }

    /**
     * @return string The host PHP binary, the oracle.
     */
    public function phpPath(): string
    {
        return $this->php;
    }

    /**
     * Materialize the managed native packages pinned in `$workDir/elephc.lock`.
     *
     * @param string $workDir Directory holding `elephc.toml` and `elephc.lock`.
     *
     * @return ProcessResult The install's result.
     */
    public function installPackages(string $workDir): ProcessResult
    {
        return $this->processRunner->run([$this->elephc, 'native', 'install', '--locked'], $workDir, $this->env);
    }

    /**
     * The job that compiles and links `$dir/main.php` to `$dir/main`.
     *
     * @param string $dir Probe directory.
     *
     * @return ProcessJob The compiler's job.
     */
    public function compileJob(string $dir): ProcessJob
    {
        return new ProcessJob([$this->elephc, 'main.php'], $dir, $this->env);
    }

    /**
     * Compile `$dir/main.php` as a web server, `$dir/main` (gate:23): elephc's
     * prefork server with persistent handler processes (`--web-isolation=pool`),
     * the model its PDO drivers run under.
     *
     * @param string $dir The site's work directory.
     *
     * @return ProcessResult The compiler's result.
     */
    public function compileWeb(string $dir): ProcessResult
    {
        return $this->processRunner->run([$this->elephc, '--web', '--web-isolation=pool', 'main.php'], $dir, $this->env);
    }

    /**
     * The job that runs the native binary `$dir/main`.
     *
     * @param string $dir Probe directory.
     *
     * @return ProcessJob The binary's job.
     */
    public function runNativeJob(string $dir): ProcessJob
    {
        return new ProcessJob(['timeout', self::RUN_TIMEOUT, $dir . '/main'], $dir, $this->env);
    }

    /**
     * The job that runs `$dir/main.php` under host PHP.
     *
     * @param string $dir Probe directory.
     *
     * @return ProcessJob PHP's job.
     */
    public function runPhpJob(string $dir): ProcessJob
    {
        return new ProcessJob(['timeout', self::RUN_TIMEOUT, $this->php, 'main.php'], $dir, $this->env);
    }

    /**
     * What the probes' results depend on outside the sources, as one line per
     * file: path, size, modification time and inode of the compiler, the
     * bridge libraries it links beside it, and the PHP oracle. A rebuild or an
     * upgrade writes new files, so any change to them changes the identity;
     * stat stands in for the content, which takes seconds to hash.
     *
     * @return string The identity.
     */
    public function identity(): string
    {
        $libraries = glob(dirname($this->elephc) . '/libelephc_*.a');
        $lines = [];

        foreach ([$this->elephc, ...$libraries === false ? [] : $libraries, $this->php] as $file) {
            $stat = stat($file);
            $lines[] = $stat === false
                ? $file . ' missing'
                : sprintf('%s %d %d %d', $file, $stat['size'], $stat['mtime'], $stat['ino']);
        }

        return implode("\n", $lines);
    }
}
