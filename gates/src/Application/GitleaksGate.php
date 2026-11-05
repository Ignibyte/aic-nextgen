<?php

declare(strict_types=1);

namespace Gates\Application;

use function fwrite;
use function is_string;
use function sprintf;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #17 — gitleaks secret scan.
 *
 * Resolves a `gitleaks` binary in priority order (project-local pin → user
 * `~/.local/bin` → `command -v` PATH lookup) and invokes it via the
 * {@see ProcessRunner} seam. Fails loudly if no binary is found rather than
 * silently passing.
 */
final readonly class GitleaksGate
{
    /**
     * @param ExternalTool $externalTool Locates and runs the tool binary.
     * @param string       $package      ignibyte/aic-gates' directory: its base configs and elephc pins (#116).
     * @param string       $home         Resolved HOME-env value (read at the wrapper boundary, never inside the class).
     */
    public function __construct(
        private ExternalTool $externalTool,
        private string $package,
        private string $home = '',
    ) {
    }

    /**
     * Run the gitleaks scan and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path the scan targets.
     * @param resource $stdout Stream success/preview lines are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $bin = $this->externalTool->locate('gitleaks', [$root . '/tools/gitleaks', $this->home . '/.local/bin/gitleaks']);

        if (!is_string($bin)) {
            fwrite($stderr, $this->renderMissingBinary());

            return 1;
        }

        return $this->externalTool->run('gate:17', $this->buildCommand($bin, $root), $stdout, $stderr);
    }

    /**
     * Build the array-form command for `proc_open`.
     *
     * @param string $bin  Resolved gitleaks binary path.
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function buildCommand(string $bin, string $root): array
    {
        return [
            $bin,
            'detect',
            '--source=' . $root,
            '--config=' . $this->package . '/config/gitleaks.toml',
            '--redact',
            '--no-banner',
            '--exit-code=1',
        ];
    }

    /**
     * Render the missing-binary error block as one deterministic string.
     */
    private function renderMissingBinary(): string
    {
        return sprintf(
            'gate:17 — gitleaks binary not found.%sInstall via: vendor/bin/aic-gate tools, or pin a version under ./tools/gitleaks%s',
            PHP_EOL,
            PHP_EOL,
        );
    }
}
