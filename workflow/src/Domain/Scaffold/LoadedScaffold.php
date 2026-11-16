<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Scaffold;

/**
 * A scaffold the loader checked (#298): its manifest and the text of every
 * template it names, by file name.
 */
final readonly class LoadedScaffold
{
    /**
     * @param ScaffoldManifest      $scaffoldManifest The manifest.
     * @param array<string, string> $templates        Each template's text, by its file name.
     */
    public function __construct(
        public ScaffoldManifest $scaffoldManifest,
        public array $templates,
    ) {
    }

    /**
     * @param string $name A template's file name.
     *
     * @return string Its text; empty when the manifest names no such template.
     */
    public function template(string $name): string
    {
        return $this->templates[$name] ?? '';
    }
}
