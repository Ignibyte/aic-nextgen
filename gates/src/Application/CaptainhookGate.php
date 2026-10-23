<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;
use JsonException;

use function count;
use function file_get_contents;
use function fwrite;
use function implode;
use function is_dir;
use function is_executable;
use function is_file;
use function is_readable;
use function is_string;
use function sprintf;
use function str_contains;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #20 — Captainhook installation verification.
 *
 * Proves three things in one pass:
 *   1. Every hook declared `enabled: true` in `captainhook.json` has a
 *      corresponding file in `.git/hooks/` that references CaptainHook.
 *   2. `captainhook config:info` parses `captainhook.json` without error.
 *   3. The installed hook files reference CaptainHook (not stale stubs).
 *
 * `config:validate` does not exist in CaptainHook 5.29.x; this gate is
 * the AIC-side replacement specified in ADR 0005 row 18.
 */
final readonly class CaptainhookGate
{
    /**
     * @param ProcessRunner           $processRunner           Seam wrapping `proc_open`.
     * @param CaptainhookConfigReader $captainhookConfigReader Reads the enabled hooks from `captainhook.json`.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private CaptainhookConfigReader $captainhookConfigReader = new CaptainhookConfigReader(),
    ) {
    }

    /**
     * Run the gate against `$root` and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path.
     * @param resource $stdout Stream success/preview lines are written to.
     * @param resource $stderr Stream failure messages are written to.
     *
     * @throws JsonException When captainhook.json is malformed.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $configPath = $root . '/captainhook.json';
        $expectedHooks = $this->captainhookConfigReader->enabledHooks($configPath, $stderr);

        if ($expectedHooks === null) {
            return 1;
        }

        if (count($expectedHooks) < 1) {
            fwrite($stderr, 'gate:20 — captainhook.json declares no enabled hooks; nothing to verify.' . PHP_EOL);

            return 1;
        }

        $installCheck = $this->verifyInstalledHooks($root, $expectedHooks, $stderr);

        if ($installCheck > 0) {
            return $installCheck;
        }

        return $this->runCaptainhookInfo($root, $configPath, $expectedHooks, $stdout, $stderr);
    }

    /**
     * Verify each expected hook exists under `.git/hooks/` and references CaptainHook.
     *
     * @param string       $root          Repo-root absolute path.
     * @param list<string> $expectedHooks Hook names to verify.
     * @param resource     $stderr        Stream failure messages are written to.
     */
    private function verifyInstalledHooks(string $root, array $expectedHooks, mixed $stderr): int
    {
        $gitHooksDir = $root . '/.git/hooks';

        if (!is_dir($gitHooksDir)) {
            fwrite($stderr, 'gate:20 — .git/hooks/ not found; run `composer install` to install hooks.' . PHP_EOL);

            return 1;
        }

        [$missing, $unreferenced] = $this->classifyHookFiles($gitHooksDir, $expectedHooks);
        $missingCount = count($missing);
        $unreferencedCount = count($unreferenced);

        if ($missingCount < 1 && $unreferencedCount < 1) {
            return 0;
        }

        fwrite($stderr, $this->renderInstalledHookFailures($missing, $unreferenced));

        return 1;
    }

    /**
     * Render the missing-file + unreferenced-hook diagnostic block as one
     * deterministic string.
     *
     * @param list<string> $missing      Hook names with no installed file.
     * @param list<string> $unreferenced Hook names whose installed file does not mention CaptainHook.
     */
    private function renderInstalledHookFailures(array $missing, array $unreferenced): string
    {
        $missingLine = $missing !== []
            ? 'gate:20 — missing hook files in .git/hooks/: ' . implode(', ', $missing) . PHP_EOL
            : '';
        $unreferencedLine = $unreferenced !== []
            ? 'gate:20 — installed but not CaptainHook-managed: ' . implode(', ', $unreferenced) . PHP_EOL
            : '';

        return $missingLine
            . $unreferencedLine
            . 'Fix: run `composer install`; captainhook/hook-installer installs the hooks.' . PHP_EOL;
    }

    /**
     * Partition `$expectedHooks` into missing-file vs not-CaptainHook-managed buckets.
     *
     * @param string       $gitHooksDir   Absolute path to `.git/hooks/`.
     * @param list<string> $expectedHooks Hook names to inspect.
     *
     * @return array{0: list<string>, 1: list<string>} Tuple `[missing, unreferenced]`.
     */
    private function classifyHookFiles(string $gitHooksDir, array $expectedHooks): array
    {
        $missing = [];
        $unreferenced = [];

        foreach ($expectedHooks as $expectedHook) {
            $path = $gitHooksDir . '/' . $expectedHook;

            if (!is_file($path)) {
                $missing[] = $expectedHook;

                continue;
            }

            if ($this->referencesCaptainhook($path)) {
                continue;
            }

            $unreferenced[] = $expectedHook;
        }

        return [$missing, $unreferenced];
    }

    /**
     * Decide whether the hook file at `$path` mentions CaptainHook (case-insensitive).
     *
     * @param string $path Absolute path to a hook file.
     */
    private function referencesCaptainhook(string $path): bool
    {
        if (!is_readable($path)) {
            return false;
        }

        $contents = (string) file_get_contents($path);

        return str_contains($contents, 'CaptainHook') || str_contains($contents, 'captainhook');
    }

    /**
     * Invoke `captainhook config:info` and verify each expected hook appears in its output.
     *
     * @param string       $root          Repo-root absolute path.
     * @param string       $configPath    Absolute path to `captainhook.json`.
     * @param list<string> $expectedHooks Hook names that must appear in the output.
     * @param resource     $stdout        Stream success/preview lines are written to.
     * @param resource     $stderr        Stream failure messages are written to.
     */
    private function runCaptainhookInfo(
        string $root,
        string $configPath,
        array $expectedHooks,
        mixed $stdout,
        mixed $stderr,
    ): int {
        $bin = $root . '/vendor/bin/captainhook';

        if (!is_executable($bin)) {
            fwrite($stderr, 'gate:20 — vendor/bin/captainhook not found. Run `composer install`.' . PHP_EOL);

            return 1;
        }

        $command = [$bin, 'config:info', '--configuration=' . $configPath];
        $processResult = $this->processRunner->run($command);
        $merged = $processResult->stdout . $processResult->stderr;
        fwrite($stdout, sprintf('gate:20 — %s%s%s', implode(' ', $command), PHP_EOL, $merged));

        return $this->verifyInfoOutput($merged, $expectedHooks, $stdout, $stderr);
    }

    /**
     * Verify `captainhook config:info` output names every expected hook.
     *
     * @param string       $output        Combined stdout+stderr from the captainhook CLI call.
     * @param list<string> $expectedHooks Hook names that must appear.
     * @param resource     $stdout        Stream the success summary is written to.
     * @param resource     $stderr        Stream the failure message is written to.
     */
    private function verifyInfoOutput(string $output, array $expectedHooks, mixed $stdout, mixed $stderr): int
    {
        $failure = $this->detectInfoFailure($output, $expectedHooks);

        if (is_string($failure)) {
            fwrite($stderr, $failure);

            return 1;
        }

        fwrite($stdout, sprintf(
            'gate:20 — verified %d enabled hook(s): %s%s',
            count($expectedHooks),
            implode(', ', $expectedHooks),
            PHP_EOL,
        ));

        return 0;
    }

    /**
     * Return the rendered failure message, or `null` when `$output` proves the run was healthy.
     *
     * @param string       $output        Combined stdout+stderr from the captainhook CLI call.
     * @param list<string> $expectedHooks Hook names that must appear.
     */
    private function detectInfoFailure(string $output, array $expectedHooks): ?string
    {
        if (!str_contains($output, 'CaptainHook version')) {
            return sprintf(
                'gate:20 — captainhook config:info did not produce the expected header; configuration parse may have failed.%s',
                PHP_EOL,
            );
        }

        foreach ($expectedHooks as $expectedHook) {
            if (!str_contains($output, $expectedHook)) {
                return sprintf(
                    'gate:20 — captainhook config:info did not list expected hook `%s`.%s',
                    $expectedHook,
                    PHP_EOL,
                );
            }
        }

        return null;
    }
}
