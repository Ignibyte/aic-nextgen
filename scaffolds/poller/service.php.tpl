<?php

declare(strict_types=1);

namespace {namespace}\Service;

/**
 * Reads the {name|kebab} upstream system for what changed, made by bin/aic
 * make poller (#298). The made body answers at once; the module writes the
 * read.
 */
final readonly class {name}Poller
{
    /**
     * @return bool Whether the upstream answered.
     */
    public function poll(): bool
    {
        return true;
    }
}
