<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

/**
 * One component's run in rendered markup (#70): its path below the region,
 * and where its opening marker starts and its closing marker ends.
 */
final readonly class Run
{
    /**
     * @param string $path  The keys of it and its enclosing components, outermost first, joined by `/`.
     * @param int    $start Where its opening marker starts.
     * @param int    $end   Just after its closing marker.
     */
    public function __construct(
        public string $path,
        public int $start,
        public int $end,
    ) {
    }
}
