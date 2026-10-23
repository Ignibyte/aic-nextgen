<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;
use RuntimeException;

use function fwrite;

use const PHP_BINARY;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate 13: complete ordinary analysis followed by complete taint analysis.
 * Both attempts run, and neither failure can be hidden by the other pass.
 */
final readonly class PsalmGate
{
    /**
     * @param ProcessRunner $processRunner Captures each analysis process.
     */
    public function __construct(private ProcessRunner $processRunner)
    {
    }

    /**
     * @param string $root The consuming project's root.
     *
     * @return int Zero only when both analyses exit zero.
     */
    public function run(string $root): int
    {
        $ordinary = $this->runPass($root, 'ordinary', []);
        $taint = $this->runPass($root, 'taint', ['--taint-analysis']);

        return $ordinary && $taint
            ? 0
            : 1;
    }

    /**
     * @param string             $root  The consuming project's root.
     * @param 'ordinary'|'taint' $mode  The analysis being attempted.
     * @param list<string>       $extra The mode's explicit flags.
     *
     * @return bool Whether this analysis produced a successful process result.
     */
    private function runPass(string $root, string $mode, array $extra): bool
    {
        fwrite(STDOUT, 'gate:13 — ' . $mode . ' analysis (full project).' . PHP_EOL);

        try {
            $processResult = $this->processRunner->run($this->command($root, $extra), $root);
        } catch (RuntimeException $runtimeException) {
            fwrite(STDERR, 'gate:13 — ' . $mode . ' process error: ' . $runtimeException->getMessage() . PHP_EOL);

            return false;
        }

        fwrite(STDOUT, $processResult->stdout);
        fwrite(STDERR, $processResult->stderr);
        fwrite(STDOUT, PHP_EOL . 'gate:13 — ' . $mode . ' exit ' . $processResult->exitCode . '.' . PHP_EOL);

        return $processResult->exitCode === 0;
    }

    /**
     * @param string       $root  The consuming project's root.
     * @param list<string> $extra The mode's explicit flags.
     *
     * @return list<string> One full-project invocation against the same config.
     */
    private function command(string $root, array $extra): array
    {
        return [
            PHP_BINARY,
            $root . '/vendor/bin/psalm',
            '--config=' . $root . '/psalm.xml',
            '--no-progress',
            '--show-info=true',
            '--no-cache',
            '--no-diff',
            ...$extra,
        ];
    }
}
