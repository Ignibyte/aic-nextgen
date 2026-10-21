<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One command for a `ProcessPool`: what to run, where, and with which environment.
 */
final readonly class ProcessJob
{
    /**
     * @param list<string>          $command Command + arguments (no shell string).
     * @param string                $cwd     Working directory for the child process.
     * @param array<string, string> $env     Environment for the child, passed verbatim.
     */
    public function __construct(
        public array $command,
        public string $cwd,
        public array $env,
    ) {
    }
}
