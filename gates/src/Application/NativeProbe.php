<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeProbeResult;
use Gates\Domain\NativeProbeStatus;
use RuntimeException;

use function array_find;
use function explode;
use function file_put_contents;
use function filesize;
use function implode;
use function is_dir;
use function is_file;
use function mb_substr;
use function mb_trim;
use function mkdir;
use function preg_match;
use function sprintf;
use function str_replace;
use function unlink;
use function var_export;

use const PHP_EOL;

/**
 * Probes one runtime class natively (gate:21): compile, link, run, compare with PHP.
 *
 * Each root gets a directory with `_bundle.php` (its closure, dependencies
 * first) and a `main.php` that loads it and echoes `ok`. The guarded `eval`
 * forces whole-program lowering: upstream elephc prunes every declaration the
 * entry point cannot reach, and without it the probe would lower almost
 * nothing. Neither PHP nor the binary takes the branch.
 */
final readonly class NativeProbe
{
    /**
     * A compiler output line that names the error.
     */
    private const string ERROR_LINE = '/^error\\[|error:|undefined reference|EIR backend error/';

    /**
     * The program every probe runs: load the closure, force lowering, echo ok.
     */
    private const string MAIN = <<<'PHP'
        <?php

        declare(strict_types=1);

        require __DIR__ . '/_bundle.php';

        if (getenv('AIC_NATIVE_GATE_NEVER') === 'set') {
            eval('');
        }

        echo 'ok';

        PHP;

    /**
     * @param NativeToolchain $nativeToolchain The compiler, its environment and the PHP oracle.
     */
    public function __construct(
        private NativeToolchain $nativeToolchain,
    ) {
    }

    /**
     * Probe one runtime class.
     *
     * @param string       $root    FQCN of the runtime class.
     * @param list<string> $files   Its closure, dependencies first.
     * @param string       $workDir The gate's work directory.
     *
     * @throws RuntimeException When the probe directory cannot be written.
     *
     * @return NativeProbeResult How far the probe got.
     */
    public function probe(string $root, array $files, string $workDir): NativeProbeResult
    {
        $dir = $this->writeProgram($root, $files, $workDir);

        return $this->buildFailure($root, $dir)
            ?? $this->runFailure($root, $dir)
            ?? new NativeProbeResult($root, NativeProbeStatus::Ok);
    }

    /**
     * Compile and link; return the failure, or null when a binary was built.
     *
     * @param string $root FQCN of the runtime class.
     * @param string $dir  Probe directory.
     *
     * @return NativeProbeResult|null The COMPILE_FAIL or LINK_FAIL result.
     */
    private function buildFailure(string $root, string $dir): ?NativeProbeResult
    {
        $processResult = $this->nativeToolchain->compile($dir);

        if ($processResult->exitCode !== 0) {
            $detail = $this->firstError($processResult->stdout . $processResult->stderr);

            return new NativeProbeResult($root, NativeProbeStatus::CompileFail, $detail);
        }

        if (!is_file($dir . '/main') || filesize($dir . '/main') === 0) {
            return new NativeProbeResult($root, NativeProbeStatus::LinkFail, 'no binary emitted despite exit 0');
        }

        return null;
    }

    /**
     * Run the binary and PHP; return the failure, or null when the outputs match.
     *
     * @param string $root FQCN of the runtime class.
     * @param string $dir  Probe directory.
     *
     * @return NativeProbeResult|null The RUN_FAIL or MISMATCH result.
     */
    private function runFailure(string $root, string $dir): ?NativeProbeResult
    {
        $processResult = $this->nativeToolchain->runNative($dir);
        $php = $this->nativeToolchain->runPhp($dir);

        if ($processResult->exitCode !== 0) {
            $detail = sprintf('exit %d: %s', $processResult->exitCode, $this->clip($processResult->stdout . $processResult->stderr));

            return new NativeProbeResult($root, NativeProbeStatus::RunFail, $detail);
        }

        if ($processResult->stdout !== $php->stdout) {
            $detail = sprintf('native=%s php=%s', $this->clip($processResult->stdout), $this->clip($php->stdout));

            return new NativeProbeResult($root, NativeProbeStatus::Mismatch, $detail);
        }

        return null;
    }

    /**
     * Write `_bundle.php` and `main.php` for one root; return its directory.
     *
     * @param string       $root    FQCN of the runtime class.
     * @param list<string> $files   Its closure, dependencies first.
     * @param string       $workDir The gate's work directory.
     *
     * @throws RuntimeException When the directory or a file cannot be written.
     *
     * @return string The probe directory.
     */
    private function writeProgram(string $root, array $files, string $workDir): string
    {
        $dir = $workDir . '/roots/' . str_replace('\\', '_', $root);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
            throw new RuntimeException('gate:21 — cannot create ' . $dir);
        }

        if (is_file($dir . '/main')) {
            unlink($dir . '/main');
        }

        $written = file_put_contents($dir . '/_bundle.php', $this->bundleSource($root, $files)) !== false
            && file_put_contents($dir . '/main.php', self::MAIN) !== false;

        if (!$written) {
            throw new RuntimeException('gate:21 — cannot write the probe for ' . $root);
        }

        return $dir;
    }

    /**
     * The `_bundle.php` source: one `require_once` per closure file, dependencies first.
     *
     * @param string       $root  FQCN of the runtime class.
     * @param list<string> $files Its closure, dependencies first.
     *
     * @return string PHP source.
     */
    private function bundleSource(string $root, array $files): string
    {
        $lines = ['<?php', '', 'declare(strict_types=1);', '', '// The class-graph closure of ' . $root . ', dependencies first.', ''];

        foreach ($files as $file) {
            $lines[] = 'require_once ' . var_export($file, true) . ';';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * The first error line of compiler output, else its first line.
     *
     * @param string $output Compiler stdout and stderr.
     *
     * @return string The line to report.
     */
    private function firstError(string $output): string
    {
        $lines = explode("\n", $output);
        $error = array_find(
            $lines,
            static fn (string $line): bool => preg_match(self::ERROR_LINE, $line) === 1,
        );
        $first = array_find($lines, static fn (string $line): bool => mb_trim($line) !== '');

        return $this->clip($error ?? $first ?? 'compiler failed with no output');
    }

    /**
     * Trim output to one short line for the report.
     *
     * @param string $text Output text.
     *
     * @return string At most 160 characters, newlines flattened.
     */
    private function clip(string $text): string
    {
        return mb_substr(str_replace(["\r", "\n"], ' ', mb_trim($text)), 0, 160);
    }
}
