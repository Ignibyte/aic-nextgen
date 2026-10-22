<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

/**
 * Writes one of the scenario's fixtures to a path in the app, as the
 * operator (#119). It holds when the file is written.
 */
final readonly class WriteStep implements ProbeStep
{
    /**
     * @param string $path    The path in the app, such as `stray.txt`.
     * @param string $fixture The scenario's fixture file, such as `stray.txt.fixture`.
     */
    public function __construct(
        public string $path,
        public string $fixture,
    ) {
    }

    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'write ' . $this->path;
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
