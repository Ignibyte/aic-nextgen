<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Domain\ProcessResult;
use AIC\Workflow\Infrastructure\LocalProcess;

use function escapeshellarg;

use const PHP_BINARY;

/** Run inspected commands at the project root without native transport input. */
final readonly class CodexShell
{
    /**
     * @param string $root     The project root.
     * @param string $package  The protected installed package.
     * @param string $autoload The actual project autoloader.
     */
    public function __construct(private string $root, private string $package, private string $autoload)
    {
    }

    /**
     * @param string $command The original command that passed the walls.
     *
     * @return string The owned native replacement.
     */
    public function command(string $command): string
    {
        return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->package . '/bin/aic-codex-shell.php')
            . ' ' . escapeshellarg($this->root) . ' ' . escapeshellarg($this->autoload) . ' ' . escapeshellarg($command);
    }

    /**
     * @param string $command The previously inspected command.
     *
     * @return ProcessResult Complete observed output and the real exit or bounded timeout.
     */
    public function run(string $command): ProcessResult
    {
        return (new LocalProcess($this->root))->run(['/bin/bash', '--noprofile', '--norc', '-c', $command], 600);
    }
}
