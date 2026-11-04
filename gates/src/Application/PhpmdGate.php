<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;
use RuntimeException;

use function bin2hex;
use function fwrite;
use function implode;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

use const PHP_BINARY;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate 10: PHPMD over the class roots with every ruleset, judging this tree
 * only (#256). PHPMD's PDepend keeps an analyzer cache under `$HOME/.pdepend`,
 * outside the project, and replayed another tree's metrics there; so each run
 * hands PHPMD a fresh home of its own and removes it after. A cold run costs a
 * few seconds; a verdict that reads stale metrics costs more.
 */
final readonly class PhpmdGate
{
    /**
     * @param ProcessRunner  $processRunner  Runs PHPMD.
     * @param ProjectProfile $projectProfile Gives the class roots.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * @param string                $root        The consuming project's root.
     * @param string                $package     This package's directory, whose `config/` holds the rulesets.
     * @param array<string, string> $environment The gate's environment, which PHPMD inherits with its own home.
     *
     * @return int PHPMD's exit code; 1 when it cannot run.
     */
    public function run(string $root, string $package, array $environment): int
    {
        $home = sys_get_temp_dir() . '/aic-phpmd-' . bin2hex(random_bytes(6));

        if (!mkdir($home, 0o700, true)) {
            fwrite(STDERR, 'gate:10 — cannot make a home for PDepend at ' . $home . '.' . PHP_EOL);

            return 1;
        }

        try {
            $processResult = $this->processRunner->run($this->command($root, $package), $root, [...$environment, 'HOME' => $home]);
        } catch (RuntimeException $runtimeException) {
            fwrite(STDERR, 'gate:10 — PHPMD did not run: ' . $runtimeException->getMessage() . PHP_EOL);

            return 1;
        } finally {
            $this->remove($home);
        }

        fwrite(STDOUT, $processResult->stdout);
        fwrite(STDERR, $processResult->stderr);

        return $processResult->exitCode;
    }

    /**
     * @param string $root    The consuming project's root.
     * @param string $package This package's directory.
     *
     * @return list<string> PHPMD over the class roots, with the base and custom rulesets, strict.
     */
    private function command(string $root, string $package): array
    {
        return [
            PHP_BINARY,
            $root . '/vendor/bin/phpmd',
            implode(',', $this->projectProfile->roots($root)->classRoots),
            'text',
            $package . '/config/phpmd.xml,' . $package . '/config/phpmd-custom.xml',
            '--strict',
        ];
    }

    /**
     * @param string $directory The home this run made.
     */
    private function remove(string $directory): void
    {
        try {
            $this->processRunner->run(['rm', '-rf', '--', $directory]);
        } catch (RuntimeException $runtimeException) {
            fwrite(STDERR, 'gate:10 — could not remove ' . $directory . ': ' . $runtimeException->getMessage() . PHP_EOL);
        }
    }
}
