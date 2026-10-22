<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

/**
 * Serves the app with `php -S` on the port its config names as `site`
 * (#119), until a `StopStep`. It holds when the server answers.
 */
final readonly class ServeStep implements ProbeStep
{
    /**
     * @param array<string, string> $env Variables it serves with, such as `PGPORT` => `1`.
     */
    public function __construct(
        public array $env = [],
    ) {
    }

    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'serve the app';
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
