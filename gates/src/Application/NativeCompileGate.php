<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeProbeResult;
use Gates\Domain\NativeProbeStatus;
use RuntimeException;

use function array_filter;
use function array_map;
use function copy;
use function count;
use function fwrite;
use function is_dir;
use function mkdir;
use function sprintf;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #21 — the native gate: every runtime class compiles, links, runs and matches PHP under elephc.
 *
 * Each runtime class (`RuntimeClassFinder`) is probed on its own with its
 * class-graph closure (`ClosureBundler`), so one failure names one class.
 * A class whose closure and toolchain hash as at its last green probe
 * (`ClosureFingerprint`, `NativeProbeCache`) is reported `CACHED` and not
 * probed again, so a change reprobes only the classes whose closure holds a
 * changed file; the rest are probed in parallel (`NativeProbe`).
 * `elephc --check` is not enough: it runs only the type checker and passes
 * code that cannot be built. Compile, link and run against the PHP oracle is
 * the truth.
 *
 * The managed native packages (pcre2, oniguruma) are pinned in
 * `gates/native/elephc.toml` and `elephc.lock`, copied into the work
 * directory `.native-gate/`, where elephc finds them by ancestor walk.
 */
final readonly class NativeCompileGate
{
    /**
     * The native-package manifest and lock, relative to the repo root.
     */
    private const array PACKAGE_FILES = ['elephc.toml', 'elephc.lock'];

    /**
     * @param NativeToolchain    $nativeToolchain    The compiler, its environment and the PHP oracle.
     * @param NativeProbe        $nativeProbe        Probes one runtime class.
     * @param RuntimeClassFinder $runtimeClassFinder Lists the runtime corpus.
     * @param ClosureBundler     $closureBundler     Collects a class's closure.
     * @param ComposerPsr4Loader $composerPsr4Loader Loads composer's PSR-4 map.
     * @param ClosureFingerprint $closureFingerprint Keys a closure for the cache.
     * @param NativeProbeCache   $nativeProbeCache   The keys of the last run's green probes.
     */
    public function __construct(
        private NativeToolchain $nativeToolchain,
        private NativeProbe $nativeProbe,
        private RuntimeClassFinder $runtimeClassFinder = new RuntimeClassFinder(),
        private ClosureBundler $closureBundler = new ClosureBundler(),
        private ComposerPsr4Loader $composerPsr4Loader = new ComposerPsr4Loader(),
        private ClosureFingerprint $closureFingerprint = new ClosureFingerprint(),
        private NativeProbeCache $nativeProbeCache = new NativeProbeCache(),
    ) {
    }

    /**
     * Probe every runtime class and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream the per-class results and the summary are written to.
     * @param resource $stderr Stream failures are written to.
     *
     * @throws RuntimeException When the work directory, a probe or the cache cannot be written.
     *
     * @return int The exit code.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $classes = $this->runtimeClassFinder->find($root);

        if ($classes === []) {
            fwrite($stdout, 'gate:21 — no runtime classes under framework/src or modules yet; deferring the native gate.' . PHP_EOL);

            return 0;
        }

        if (!$this->nativeToolchain->isAvailable()) {
            $compiler = $this->nativeToolchain->compilerPath();
            fwrite($stderr, sprintf('gate:21 — elephc not found at %s (set ELEPHC_BIN).%s', $compiler, PHP_EOL));

            return 1;
        }

        $workDir = $this->prepareWorkDir($root, $stderr);

        if ($workDir === null) {
            return 1;
        }

        return $this->report($this->probeAll($classes, $root, $workDir), $stdout, $stderr);
    }

    /**
     * Probe each runtime class whose key is not in the cache, and record the keys of every class that passed.
     *
     * @param list<string> $classes Runtime FQCNs.
     * @param string       $root    Repo-root absolute path.
     * @param string       $workDir The gate's work directory.
     *
     * @throws RuntimeException When a closure, a probe or the cache cannot be read or written.
     *
     * @return list<NativeProbeResult> One result per class, in class order.
     */
    private function probeAll(array $classes, string $root, string $workDir): array
    {
        $keys = $this->keys($classes, $root);
        $cached = $this->nativeProbeCache->load($workDir);
        $results = [];
        $closures = [];

        foreach ($keys as $class => $key) {
            if (($cached[$class] ?? '') === $key['key']) {
                $results[$class] = new NativeProbeResult($class, NativeProbeStatus::Cached);

                continue;
            }

            $closures[$class] = $key['files'];
        }

        foreach ($this->nativeProbe->probeAll($closures, $workDir) as $nativeProbeResult) {
            $results[$nativeProbeResult->root] = $nativeProbeResult;
        }

        $ordered = array_map(static fn (string $class): NativeProbeResult => $results[$class], $classes);
        $this->record($ordered, $keys, $workDir);

        return $ordered;
    }

    /**
     * Rewrite the cache with the key of every class that passed.
     *
     * @param list<NativeProbeResult>                                $results This run's results.
     * @param array<string, array{files: list<string>, key: string}> $keys    Closure and key by FQCN.
     * @param string                                                 $workDir The gate's work directory.
     *
     * @throws RuntimeException When the cache cannot be written.
     */
    private function record(array $results, array $keys, string $workDir): void
    {
        $green = [];

        foreach ($results as $result) {
            if (!$result->passed()) {
                continue;
            }

            $green[$result->root] = $keys[$result->root]['key'];
        }

        $this->nativeProbeCache->save($workDir, $green);
    }

    /**
     * Each class's closure and its cache key.
     *
     * @param list<string> $classes Runtime FQCNs.
     * @param string       $root    Repo-root absolute path.
     *
     * @throws RuntimeException When a file in a closure cannot be read.
     *
     * @return array<string, array{files: list<string>, key: string}> Closure and key by FQCN.
     */
    private function keys(array $classes, string $root): array
    {
        $psr4Map = $this->composerPsr4Loader->load($root);
        $packageFiles = array_map(static fn (string $file): string => $root . '/gates/native/' . $file, self::PACKAGE_FILES);
        $toolchain = $this->closureFingerprint->toolchain($this->nativeToolchain->identity(), $packageFiles, NativeProbe::MAIN);
        $keys = [];

        foreach ($classes as $class) {
            $files = $this->closureBundler->closure($class, $psr4Map);
            $keys[$class] = ['files' => $files, 'key' => $this->closureFingerprint->closure($toolchain, $files)];
        }

        return $keys;
    }

    /**
     * Create the work directory, copy the package files in and install the packages.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stderr Stream failures are written to.
     *
     * @throws RuntimeException When the work directory cannot be created.
     *
     * @return string|null The work directory, or null when the packages cannot be installed.
     */
    private function prepareWorkDir(string $root, mixed $stderr): ?string
    {
        $workDir = $root . '/.native-gate';

        if (!is_dir($workDir) && !mkdir($workDir, 0o755, true)) {
            throw new RuntimeException('gate:21 — cannot create ' . $workDir);
        }

        $this->copyPackageFiles($root, $workDir);
        $processResult = $this->nativeToolchain->installPackages($workDir);

        if ($processResult->exitCode !== 0) {
            $output = $processResult->stdout . $processResult->stderr;
            fwrite($stderr, sprintf('gate:21 — elephc native install --locked failed:%s%s', PHP_EOL, $output));

            return null;
        }

        return $workDir;
    }

    /**
     * Copy `elephc.toml` and `elephc.lock` into the work directory, where elephc finds them.
     *
     * @param string $root    Repo-root absolute path.
     * @param string $workDir The gate's work directory.
     *
     * @throws RuntimeException When a file cannot be copied.
     */
    private function copyPackageFiles(string $root, string $workDir): void
    {
        foreach (self::PACKAGE_FILES as $file) {
            if (!copy($root . '/gates/native/' . $file, $workDir . '/' . $file)) {
                throw new RuntimeException('gate:21 — cannot copy gates/native/' . $file);
            }
        }
    }

    /**
     * Write one line per class and the summary; return the exit code.
     *
     * @param list<NativeProbeResult> $results Probe results.
     * @param resource                $stdout  Stream the results are written to.
     * @param resource                $stderr  Stream failures are written to.
     *
     * @return int 0 when every class passed, 1 otherwise.
     */
    private function report(array $results, mixed $stdout, mixed $stderr): int
    {
        foreach ($results as $result) {
            fwrite($result->passed() ? $stdout : $stderr, $this->lineFor($result));
        }

        $failed = count(array_filter($results, static fn (NativeProbeResult $nativeProbeResult): bool => !$nativeProbeResult->passed()));
        $cached = count(array_filter(
            $results,
            static fn (NativeProbeResult $nativeProbeResult): bool => $nativeProbeResult->status === NativeProbeStatus::Cached,
        ));
        $summary = $this->summary(count($results), $failed, $cached);

        if ($failed === 0) {
            fwrite($stdout, $summary);

            return 0;
        }

        fwrite($stderr, $summary);

        return 1;
    }

    /**
     * The summary line.
     *
     * @param int $total  Runtime classes.
     * @param int $failed Classes that failed.
     * @param int $cached Classes the cache vouched for.
     *
     * @return string The line, newline included.
     */
    private function summary(int $total, int $failed, int $cached): string
    {
        return sprintf(
            'gate:21 — native: %d of %d runtime class(es) compile, link, run and match PHP (%d cached, %d probed).%s',
            $total - $failed,
            $total,
            $cached,
            $total - $cached,
            PHP_EOL,
        );
    }

    /**
     * One report line: status, class and, for a failure, what went wrong.
     *
     * @param NativeProbeResult $nativeProbeResult One probe result.
     *
     * @return string The line, newline included.
     */
    private function lineFor(NativeProbeResult $nativeProbeResult): string
    {
        $detail = $nativeProbeResult->detail === ''
            ? ''
            : ': ' . $nativeProbeResult->detail;

        return sprintf('gate:21 — %-12s %s%s%s', $nativeProbeResult->status->value, $nativeProbeResult->root, $detail, PHP_EOL);
    }
}
