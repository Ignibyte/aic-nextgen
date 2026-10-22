<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Probe;

use Override;

/**
 * Replaces one text with another in a file in the app (#119), Droost's
 * mutate-and-restore. A `from` the file does not hold is a failure that
 * ends the scenario, so a stale plant never reads as a catch; a restore is
 * a replace too, so it is proved.
 */
final readonly class ReplaceStep implements ProbeStep
{
    /**
     * @param string $path The path in the app.
     * @param string $from The text it must hold, once.
     * @param string $into The text that takes its place.
     */
    public function __construct(
        public string $path,
        public string $from,
        public string $into,
    ) {
    }

    /**
     * @return string What the step does.
     */
    #[Override]
    public function label(): string
    {
        return 'replace in ' . $this->path;
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
