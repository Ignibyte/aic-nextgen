<?php

declare(strict_types=1);

namespace Gates\Domain;

use RuntimeException;

/**
 * A throwaway copy of the project (#300): what git lists, the installed
 * packages and the tools, and the analysers' caches, so gate:33 can make a
 * scaffold and judge what it made without touching the project.
 */
interface ProjectCopier
{
    /**
     * @param string $root The project's root.
     *
     * @throws RuntimeException When the copy cannot be made.
     *
     * @return string The copy's root, absolute.
     */
    public function copy(string $root): string;

    /**
     * @param string $copy A copy's root.
     */
    public function remove(string $copy): void;
}
