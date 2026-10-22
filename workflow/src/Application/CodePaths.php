<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

/**
 * What a path in the project is (#118): the class it holds by the PSR-4
 * maps, whether anything is at it on disk, whether that is a directory, and
 * the code index's area for it.
 */
interface CodePaths
{
    /**
     * @param string $path A path, relative to the project.
     *
     * @return string The class a PHP file at it declares by PSR-4 or the module convention; empty for none.
     */
    public function classOf(string $path): string;

    /**
     * @param string $path A path, relative to the project.
     *
     * @return bool Whether a file or a directory is there.
     */
    public function exists(string $path): bool;

    /**
     * @param string $path A path, relative to the project.
     *
     * @return bool Whether a directory is there.
     */
    public function isDirectory(string $path): bool;

    /**
     * @param string $path A path, relative to the project.
     *
     * @return string Its area: `module:<m>`, `system:<S>`, `generated`, `workflow`, `gates` or `project`.
     */
    public function area(string $path): string;
}
