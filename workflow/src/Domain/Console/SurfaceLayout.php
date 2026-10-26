<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/** One of the theme's named layouts in the surface (#154, #157): a file it leaves out falls back to the theme's own. */
final readonly class SurfaceLayout
{
    /**
     * @param string       $name     Its name, as `--layout` takes it.
     * @param string       $page     Its page template; empty when it uses the theme's.
     * @param string       $html     Its document template; empty when it uses the theme's.
     * @param string       $pageData The class its page reads.
     * @param list<string> $regions  That class's regions, in order.
     */
    public function __construct(
        public string $name,
        public string $page,
        public string $html,
        public string $pageData,
        public array $regions,
    ) {
    }
}
