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
 * Gate #15 — Semgrep custom AIC ruleset.
 *
 * Resolves a `semgrep` binary (`~/.local/bin/semgrep` then PATH fallback) and
 * invokes it via the {@see ProcessRunner} seam against the project's
 * `semgrep.yml`. Fails loudly when the binary is missing.
 */
final readonly class SemgrepGate
{
    /**
     * Paths always excluded from the semgrep scan. `.claude` is scanned for its
     * hooks; only its worktrees and local settings stay out.
     */
    private const array EXCLUDES = [
        'vendor',
        'storage',
        'tools',
        '.ddev',
        '.claude/worktrees',
        '.claude/settings.local.json',
        '.playwright-mcp',
        '.native-gate',
        '.psalm-cache',
        '.rector-cache',
        'docs',
    ];

    /**
     * @param ExternalTool $externalTool Locates and runs the tool binary.
     * @param string       $home         Resolved HOME-env value (read at the wrapper boundary, never inside the class).
     */
    public function __construct(
        private ExternalTool $externalTool,
        private string $home = '',
    ) {
    }

    /**
     * Run the semgrep scan and return the CLI exit code.
     *
     * @param string   $root   Repo-root absolute path the scan targets.
     * @param resource $stdout Stream success/preview lines are written to.
     * @param resource $stderr Stream failure messages are written to.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $bin = $this->externalTool->locate('semgrep', [$this->home . '/.local/bin/semgrep']);

        if (!is_string($bin)) {
            fwrite($stderr, $this->renderMissingBinary());

            return 1;
        }

        return $this->externalTool->run('gate:15', $this->buildCommand($bin, $root), $stdout, $stderr);
    }

    /**
     * Build the array-form command for `proc_open`.
     *
     * @param string $bin  Resolved semgrep binary path.
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    private function buildCommand(string $bin, string $root): array
    {
        $command = [
            $bin,
            'scan',
            '--config=' . $root . '/semgrep.yml',
            '--error',
            '--no-git-ignore',
            '--quiet',
            '--metrics=off',
        ];

        foreach (self::EXCLUDES as $exclude) {
            $command[] = '--exclude=' . $exclude;
        }

        $command[] = $root;

        return $command;
    }

    /**
     * Render the missing-binary error block as one deterministic string.
     */
    private function renderMissingBinary(): string
    {
        return sprintf(
            'gate:15 — semgrep binary not found.%sInstall via: pip install --user semgrep==1.95.0%s',
            PHP_EOL,
            PHP_EOL,
        );
    }
}
