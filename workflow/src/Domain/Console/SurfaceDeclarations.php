<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/** The actual service and presentation declarations recorded by the build. */
final readonly class SurfaceDeclarations
{
    /**
     * @param list<array{class: string, type: string}>      $plugins     The plugins.
     * @param list<array{class: string, interface: string}> $bindings    The bindings.
     * @param list<array{class: string, position: string}>  $middleware  The middleware.
     * @param list<array<string, string>>                   $templates   Source, input, generated identities and role.
     * @param list<array<string, string>>                   $themes      Selected HTML/page templates and typed page data.
     * @param list<array<string, string>>                   $regions     Page property to region identity mappings.
     * @param list<array<string, string>>                   $themeAssets Theme-owned public asset declarations.
     */
    public function __construct(
        public array $plugins = [],
        public array $bindings = [],
        public array $middleware = [],
        public array $templates = [],
        public array $themes = [],
        public array $regions = [],
        public array $themeAssets = [],
    ) {
    }

    /** @return array<string, list<array<string, string>>> The exact presentation fields for JSON inspection. */
    public function rows(): array
    {
        return ['regions' => $this->regions, 'templates' => $this->templates,
            'themeAssets' => $this->themeAssets, 'themes' => $this->themes];
    }
}
