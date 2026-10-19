<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessResult;
use Gates\Domain\ProcessRunner;

use function is_executable;

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
     * Compile and link `$dir/main.php` to `$dir/main`.
     *
     * @param string $dir Probe directory.
     *
     * @return ProcessResult The compiler's result.
     */
    public function compile(string $dir): ProcessResult
    {
        return $this->processRunner->run([$this->elephc, 'main.php'], $dir, $this->env);
    }

    /**
     * Run the native binary `$dir/main`.
     *
     * @param string $dir Probe directory.
     *
     * @return ProcessResult The binary's result.
     */
    public function runNative(string $dir): ProcessResult
    {
        return $this->processRunner->run(['timeout', self::RUN_TIMEOUT, $dir . '/main'], $dir, $this->env);
    }

    /**
     * Run `$dir/main.php` under host PHP.
     *
     * @param string $dir Probe directory.
     *
     * @return ProcessResult PHP's result.
     */
    public function runPhp(string $dir): ProcessResult
    {
        return $this->processRunner->run(['timeout', self::RUN_TIMEOUT, $this->php, 'main.php'], $dir, $this->env);
    }
}
