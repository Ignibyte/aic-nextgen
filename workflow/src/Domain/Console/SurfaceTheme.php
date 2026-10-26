<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/** The surface's theme (#153): the folder the app's components, layouts and assets live in. */
final readonly class SurfaceTheme
{
    /**
     * @param string                                         $name       Its folder under `themes/`.
     * @param string                                         $html       The document template.
     * @param string                                         $page       The page template.
     * @param string                                         $pageData   The page's region data class.
     * @param list<string>                                   $regions    The page's regions, in order.
     * @param list<array{component: string, source: string}> $components Each component with its template.
     * @param list<SurfaceLayout>                            $layouts    Each named layout (#157).
     */
    public function __construct(
        public string $name,
        public string $html,
        public string $page,
        public string $pageData,
        public array $regions,
        public array $components,
        public array $layouts = [],
    ) {
    }
}
