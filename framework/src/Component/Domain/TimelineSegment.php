<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * One segment of a `Timeline` (#230), placed on its 0 to 1000 scale.
 */
final readonly class TimelineSegment
{
    /**
     * @param string $label Its tooltip.
     * @param string $start Where it starts, 0 to 1000.
     * @param string $width How wide it is, 0 to 1000.
     * @param string $tone  The tone its class names (`aic-tone-<tone>`).
     */
    public function __construct(
        public string $label,
        public string $start,
        public string $width,
        public string $tone,
    ) {
    }
}
