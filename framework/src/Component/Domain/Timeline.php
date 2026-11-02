<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A horizontal bar drawn as SVG (#230): segments laid end to end on a scale of
 * 1000, each with its label as a tooltip. Whoever builds it computes the
 * geometry; the template only prints it.
 */
final readonly class Timeline implements Component
{
    /**
     * @param string                $label    What the bar shows, its caption and accessible name.
     * @param list<TimelineSegment> $segments Its segments, left to right.
     */
    public function __construct(
        public string $label,
        public array $segments,
    ) {
    }
}
