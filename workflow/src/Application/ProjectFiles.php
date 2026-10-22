<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The project's files as `workflow:init` sees them (#105), every path
 * relative to the project's root.
 */
interface ProjectFiles
{
    /**
     * @param string $path A path.
     *
     * @throws WorkflowRefusal When it is there but cannot be read.
     *
     * @return string|null Its bytes; null when there is no file there.
     */
    public function read(string $path): ?string;

    /**
     * @param string $path     A path.
     * @param string $contents Its bytes from now.
     *
     * @throws WorkflowRefusal When it cannot be written whole.
     */
    public function write(string $path, string $contents): void;

    /**
     * @param string $path A file.
     *
     * @throws WorkflowRefusal When it cannot be removed.
     */
    public function remove(string $path): void;

    /**
     * @param string $path A path init would write.
     *
     * @return string What keeps init from writing a regular file there (a symlink, a directory); empty when nothing.
     */
    public function obstacle(string $path): string;
}
