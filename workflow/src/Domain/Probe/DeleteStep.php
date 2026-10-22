<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

/**
 * Deletes a path in the app (#119). It holds when the path was there and is
 * gone.
 */
final readonly class DeleteStep implements ProbeStep
{
    /**
     * @param string $path The path in the app.
     */
    public function __construct(
        public string $path,
    ) {
    }

    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'delete ' . $this->path;
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
