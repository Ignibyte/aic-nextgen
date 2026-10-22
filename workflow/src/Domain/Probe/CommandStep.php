<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

use function implode;

/**
 * Runs one command in the app (#119), such as `composer run build`. It holds
 * when the command exits as expected.
 */
final readonly class CommandStep implements ProbeStep
{
    /**
     * @param list<string>          $argv The command and its arguments.
     * @param int                   $exit The exit it must end with.
     * @param array<string, string> $env  Variables it runs with, through `env(1)`.
     */
    public function __construct(
        public array $argv,
        public int $exit = 0,
        public array $env = [],
    ) {
    }

    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'run ' . implode(' ', $this->argv);
    }

    /**
     * @return int One.
     */
    #[Override]
    public function expectations(): int
    {
        return 1;
    }
}
