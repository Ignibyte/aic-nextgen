<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One framework surface an app can use, and where it does (#232).
 */
final readonly class CoverageSurface
{
    /**
     * @param string       $group    Its group: `contracts`, `responses`, `manifest`, `schema`, `query`, `atoms` or `tessera`.
     * @param string       $name     The interface, class or `Class::method`, or the Tessera attribute, meta or event.
     * @param list<string> $files    The app files that use it, sorted.
     * @param bool         $rendered Whether AIC's own code or templates write it: a Tessera name the app gets from the framework.
     */
    public function __construct(
        public string $group,
        public string $name,
        public array $files,
        public bool $rendered = false,
    ) {
    }

    /**
     * @return bool Whether an app file uses it or AIC renders it.
     */
    public function used(): bool
    {
        return $this->files !== [] || $this->rendered;
    }
}
