<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

/**
 * Where a path the agent names lands on disk (#102): as spelled, made
 * absolute; as landed, through symlinks; and a glob's matches.
 */
interface PathResolver
{
    /**
     * @param string $path A path as the agent wrote it: absolute, relative, or `~/…`.
     * @param string $cwd  The directory it is relative to.
     *
     * @return string It absolute, `.` and `..` resolved without touching the disk.
     */
    public function absolute(string $path, string $cwd): string;

    /**
     * @param string $absolute An absolute path, which may not exist yet.
     *
     * @return string Where a write to it lands: its nearest existing ancestor's real path, and the rest; a symlink followed.
     */
    public function landed(string $absolute): string;

    /**
     * @param string $pattern A word that may be a glob.
     * @param string $cwd     The directory it is relative to.
     *
     * @return list<string> Its matches, absolute; the word itself, absolute, when nothing matches or it is no glob.
     */
    public function expand(string $pattern, string $cwd): array;
}
