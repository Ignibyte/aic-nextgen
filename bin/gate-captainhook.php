<?php

declare(strict_types=1);

use Gates\Application\CaptainhookGate;
use Gates\Infrastructure\SystemProcessRunner;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Worktree-aware root resolution. When invoked from a per-pipeline worktree
 * (`.claude/worktrees/work-<N>/`), `.git` is a file pointing at the canonical
 * git-dir; the canonical `.git/hooks/` is what CaptainHook actually installs
 * to. Pass the canonical root to the gate so its `.git/hooks` lookup finds
 * the real hooks dir.
 *
 * Ported from old AIC (AIC #171).
 */
$worktreeRoot = dirname(__DIR__);
$dotGit = $worktreeRoot . '/.git';
$gateRoot = $worktreeRoot;

if (is_file($dotGit)) {
    $contents = (string) file_get_contents($dotGit);

    if (preg_match('/^gitdir:\\s*(.+)$/m', $contents, $matches) === 1) {
        $gitDir = trim($matches[1]);

        if (!str_starts_with($gitDir, '/')) {
            $gitDir = $worktreeRoot . '/' . $gitDir;
        }

        $commonDirFile = $gitDir . '/commondir';

        if (is_file($commonDirFile)) {
            $rel = trim((string) file_get_contents($commonDirFile));
            $resolved = str_starts_with($rel, '/')
                ? $rel
                : $gitDir . '/' . $rel;
            $canonicalGitDir = realpath($resolved);

            if ($canonicalGitDir !== false) {
                $gateRoot = dirname($canonicalGitDir);
            }
        }
    }
}

exit((new CaptainhookGate(new SystemProcessRunner()))->run($gateRoot));
