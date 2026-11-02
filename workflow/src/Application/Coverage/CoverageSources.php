<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The files the coverage report reads besides the index (#232): the
 * framework's Tessera page, the app's own files, and the framework's code
 * and templates, which render some Tessera names for the app.
 */
interface CoverageSources
{
    /**
     * @throws WorkflowRefusal When the framework's `docs/tessera.md` cannot be read.
     *
     * @return string Its markdown.
     */
    public function tesseraPage(): string;

    /**
     * @param string $path A file, relative to the project.
     *
     * @return string Its bytes; empty when it cannot be read.
     */
    public function read(string $path): string;

    /**
     * @return string The framework's PHP sources and templates, joined.
     */
    public function framework(): string;
}
