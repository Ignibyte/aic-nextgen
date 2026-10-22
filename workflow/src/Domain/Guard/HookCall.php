<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

/**
 * One Claude Code hook call as the guard reads it (#102): the tool, the
 * paths its input names, its shell command, the directory the call started
 * in, and the session.
 */
final readonly class HookCall
{
    /**
     * @param string       $tool    The tool's name, such as `Write`; empty for Stop.
     * @param list<string> $paths   The paths the input names (`file_path`, `notebook_path`, `path`, `file`, `paths`).
     * @param string       $command A Bash call's command; empty otherwise.
     * @param string       $cwd     The directory the call started in.
     * @param string       $agent   The session that called.
     */
    public function __construct(
        public string $tool,
        public array $paths,
        public string $command,
        public string $cwd,
        public string $agent,
    ) {
    }
}
